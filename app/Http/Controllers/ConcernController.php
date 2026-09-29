<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConcernRequest;
use App\Models\Concern;
use App\Models\Room;
use App\Models\User;
use App\Services\ConcernService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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
        ]);
        $room = isset($filter['room']) ? Room::find($filter['room']) : null;
        // Room history shows everything; the main list starts with the concerns not yet resolved.
        $status = $filter['status'] ?? ($room ? 'all' : 'open');

        $list = Concern::with(['room', 'lease.tenant', 'assignedTo'])
            ->when($room, fn ($q) => $q->where('room_id', $room->room_id))
            ->when($status === 'open', fn ($q) => $q->where('status', '!=', 'Resolved'))
            ->when(in_array($status, Concern::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->when($filter['mine'] ?? false, fn ($q) => $q->where('assigned_to', $request->user()->user_id))
            ->orderByRaw("status = 'Resolved'")          // unresolved first
            ->orderByRaw("priority = 'Urgent' DESC")     // urgent first
            ->orderByDesc('reported_at')
            ->paginate(25)->withQueryString();

        return view('concerns.index', [
            'concerns' => $list,
            'status' => $status,
            'room' => $room,
            'mine' => (bool) ($filter['mine'] ?? false),
            'repeated' => $room ? $this->concerns->repeatedCategories(Concern::where('room_id', $room->room_id)->get()) : collect(),
        ]);
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
        $concern = $this->concerns->create($request->concernData(), $request->user(), $request->file('photo'));

        return redirect()->route('concerns.show', $concern)->with('status', 'Concern logged.');
    }

    public function show(Concern $concern): View
    {
        return view('concerns.show', ['concern' => $concern->load(['room', 'lease.tenant', 'assignedTo', 'bill'])]);
    }

    public function edit(Concern $concern): View
    {
        return view('concerns.edit', $this->formData($concern->load(['room', 'lease.tenant', 'bill'])));
    }

    public function update(ConcernRequest $request, Concern $concern): RedirectResponse
    {
        $this->concerns->update($concern, $request->concernData(), $request->user(), $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()->route('concerns.show', $concern)->with('status', 'Concern updated.');
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
            'categories' => Concern::CATEGORIES,
            'priorities' => Concern::PRIORITIES,
            'statuses' => Concern::STATUSES,
            'canSetMoney' => request()->user()->hasRole('Owner', 'Manager'),
        ];
    }
}
