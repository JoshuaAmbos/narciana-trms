<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConcernRequest;
use App\Models\Concern;
use App\Models\Room;
use App\Models\User;
use App\Services\ConcernService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Concerns: every role logs and updates (decision 15). */
class ConcernController extends Controller
{
    public function __construct(private ConcernService $concerns) {}

    public function index(Request $request): View
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(array_merge(Concern::STATUSES, ['all', 'open']))],
            'room' => ['nullable', 'integer', Rule::exists('tbl_room', 'room_id')],
            'mine' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $room = isset($filter['room']) ? Room::find($filter['room']) : null;
        $mine = (bool) ($filter['mine'] ?? false);
        // Room history shows everything; Assigned to me starts with all concerns for the user; main list starts with unresolved.
        $status = $filter['status'] ?? ($room || $mine ? 'all' : 'open');
        $search = trim((string) ($filter['search'] ?? ''));

        $baseQuery = Concern::query()->when($room, fn ($q) => $q->where('room_id', $room->room_id));
        $counts = [
            'all' => (clone $baseQuery)->count(),
            'open' => (clone $baseQuery)->where('status', '!=', 'Resolved')->count(),
            'Open' => (clone $baseQuery)->where('status', 'Open')->count(),
            'In progress' => (clone $baseQuery)->where('status', 'In progress')->count(),
            'Resolved' => (clone $baseQuery)->where('status', 'Resolved')->count(),
            'mine' => (clone $baseQuery)->where('assigned_to', $request->user()->user_id)->count(),
        ];

        $list = Concern::with(['room', 'lease.tenant', 'assignedTo', 'reportedBy', 'startedBy', 'resolvedBy', 'bill'])
            ->when($room, fn ($q) => $q->where('room_id', $room->room_id))
            ->when(! $mine && $status === 'open', fn ($q) => $q->where('status', '!=', 'Resolved'))
            ->when(! $mine && in_array($status, Concern::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->user_id))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('resolution', 'like', "%{$search}%")
                        ->orWhereHas('room', fn ($rq) => $rq->where('room_no', 'like', "%{$search}%"))
                        ->orWhereHas('lease.tenant', fn ($tq) => $tq->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("status = 'Resolved'")          // unresolved first
            ->orderByRaw("priority = 'Urgent' DESC")     // urgent first
            ->orderByDesc('reported_at')
            ->paginate(25)->withQueryString();

        $concernsMap = [];
        foreach ($list->items() as $item) {
            $concernsMap[$item->concern_id] = $this->mapConcernData($item);
        }

        $selectedConcernId = $request->integer('concern') ?: (old('concern_id') ? (int) old('concern_id') : null);
        if ($selectedConcernId && ! isset($concernsMap[$selectedConcernId])) {
            $extra = Concern::with(['room', 'lease.tenant', 'assignedTo', 'reportedBy', 'startedBy', 'resolvedBy', 'bill'])
                ->find($selectedConcernId);
            if ($extra) {
                $concernsMap[$extra->concern_id] = $this->mapConcernData($extra);
            }
        }

        $createConcern = new Concern([
            'room_id' => $room?->room_id,
            'reported_at' => now(),
            'channel' => 'Verbal',
            'priority' => 'Routine',
            'status' => 'Open',
            'assigned_to' => $request->user()->user_id,
        ]);
        $formData = $this->formData($createConcern);

        return view('concerns.index', array_merge($formData, [
            'concerns' => $list,
            'concernsMap' => $concernsMap,
            'status' => $status,
            'room' => $room,
            'search' => $search,
            'counts' => $counts,
            'mine' => (bool) ($filter['mine'] ?? false),
            'repeated' => $room ? $this->concerns->repeatedCategories(Concern::where('room_id', $room->room_id)->get()) : collect(),
            'selectedConcernId' => $request->integer('concern') ?: null,
        ]));
    }

    public function create(Request $request): View
    {
        return view('concerns.create', $this->formData(new Concern([
            'room_id' => $request->integer('room') ?: null,
            'reported_at' => now(),
            'channel' => 'Verbal',
            'priority' => 'Routine',
            'status' => 'Open',
            'assigned_to' => $request->user()->user_id,
        ])));
    }

    public function store(ConcernRequest $request): RedirectResponse
    {
        $concern = $this->concerns->create($request->concernData(), $request->user(), $this->photos($request));

        if ($request->boolean('from_index')) {
            return redirect()->route('concerns.index', ['concern' => $concern->concern_id])->with('status', 'Concern logged.');
        }

        return redirect()->route('concerns.show', $concern)->with('status', 'Concern logged.');
    }

    public function show(Concern $concern): View
    {
        return view('concerns.show', [
            'concern' => $concern->load(['room', 'lease.tenant', 'assignedTo', 'bill', 'reportedBy', 'startedBy', 'resolvedBy']),
            'timeline' => $concern->timeline(),
        ]);
    }

    /** A concern photo from private storage ($kind = reported | resolved), for logged-in users only. */
    public function photo(Concern $concern, string $kind): StreamedResponse
    {
        $path = $concern->{Concern::PHOTO_KINDS[$kind]};
        abort_unless($path && Storage::disk(ConcernService::PHOTO_DISK)->exists($path), 404);

        return Storage::disk(ConcernService::PHOTO_DISK)->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function edit(Concern $concern): View
    {
        return view('concerns.edit', $this->formData($concern->load(['room', 'lease.tenant', 'bill'])));
    }

    public function update(ConcernRequest $request, Concern $concern): RedirectResponse
    {
        $this->concerns->update($concern, $request->concernData(), $request->user(), $this->photos($request));

        if ($request->boolean('from_index')) {
            return redirect()->route('concerns.index', ['concern' => $concern->concern_id])->with('status', 'Concern updated.');
        }

        return redirect()->route('concerns.show', $concern)->with('status', 'Concern updated.');
    }

    /** The uploaded photos and "remove" ticks, for ConcernService. */
    private function photos(ConcernRequest $request): array
    {
        return [
            'photo' => $request->file('photo'),
            'remove_photo' => $request->boolean('remove_photo'),
            'resolution_photo' => $request->file('resolution_photo'),
            'remove_resolution_photo' => $request->boolean('remove_resolution_photo'),
        ];
    }

    /** Rooms with their current tenant (to fill in the lease), active users to assign, and the choice lists. */
    private function formData(Concern $concern): array
    {
        $rooms = Room::with(['leases' => fn ($q) => $q->where('status', 'Active')->with('tenant')])->orderBy('room_no')->get();

        return [
            'concern' => $concern,
            'rooms' => $rooms->map(fn (Room $r) => [
                'room_id' => $r->room_id,
                'label' => "Room {$r->room_no}",
                'lease_id' => $r->leases->first()?->lease_id,
                'tenant' => $r->leases->first()?->tenant->full_name,
            ]),
            // Also offer the concern's own lease if it has ended since (history).
            'currentLease' => $concern->lease?->loadMissing('tenant'),
            'users' => User::where('is_active', true)->orderBy('role')->orderBy('full_name')->get(),
            'channels' => Concern::CHANNELS,
            'categories' => Concern::CATEGORIES, // name => description
            'sharedCategory' => Concern::SHARED_CATEGORY,
            'priorities' => Concern::PRIORITIES,
            'statuses' => Concern::STATUSES,
            'canSetMoney' => request()->user()->hasRole('Owner', 'Manager'),
        ];
    }

    private function mapConcernData(Concern $item): array
    {
        return [
            'concern_id' => $item->concern_id,
            'location' => $item->location(),
            'room_id' => $item->room_id ? (string) $item->room_id : 'shared',
            'room_no' => $item->room?->room_no,
            'lease_id' => $item->lease_id ? (string) $item->lease_id : '',
            'tenant_name' => $item->lease?->tenant?->full_name ?? '',
            'category' => $item->category,
            'channel' => $item->channel,
            'priority' => $item->priority,
            'description' => $item->description,
            'reported_at' => $item->reported_at->format('j M Y, g:i A'),
            'reported_at_input' => $item->reported_at->format('Y-m-d\TH:i'),
            'reported_by_name' => $item->reportedBy?->full_name ?? '—',
            'reported_by_role' => $item->reportedBy?->role ?? '',
            'assigned_to' => (string) $item->assigned_to,
            'assigned_to_role' => $item->assignedTo?->role ?? '',
            'assigned_to_name' => $item->assignedTo?->full_name ?? '',
            'status' => $item->status,
            'started_at' => $item->started_at?->format('j M Y, g:i A'),
            'started_by_name' => $item->startedBy?->full_name ?? '—',
            'started_by_role' => $item->startedBy?->role ?? '',
            'resolved_at' => $item->resolved_at?->format('j M Y, g:i A'),
            'resolved_at_input' => $item->resolved_at?->format('Y-m-d\TH:i') ?? '',
            'resolved_by_name' => $item->resolvedBy?->full_name ?? '—',
            'resolved_by_role' => $item->resolvedBy?->role ?? '',
            'resolution' => $item->resolution ?? '',
            'cost' => $item->cost !== null ? (float) $item->cost : null,
            'cost_formatted' => $item->cost !== null ? number_format((float) $item->cost, 2) : '',
            'charged_to_tenant' => (bool) $item->charged_to_tenant,
            'bill_id' => $item->bill_id,
            'is_billed' => $item->isBilled(),
            'bill_due_date' => $item->bill?->due_date?->format('j M Y'),
            'bill_url' => $item->bill ? route('bills.show', $item->bill) : null,
            'photo_url' => $item->photoUrl('reported'),
            'resolution_photo_url' => $item->photoUrl('resolved'),
            'timeline' => array_map(fn ($step) => [
                'label' => $step['label'],
                'done' => (bool) $step['done'],
                'skipped' => (bool) ($step['skipped'] ?? false),
                'at' => $step['at'] ? $step['at']->format('j M Y, g:i A') : null,
                'by' => $step['by'] ? ($step['by']->full_name . ' (' . $step['by']->role . ')') : null,
            ], $item->timeline()),
            'update_url' => route('concerns.update', $item),
            'show_url' => route('concerns.show', $item),
        ];
    }
}
