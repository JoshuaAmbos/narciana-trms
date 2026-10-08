<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaseRequest;
use App\Http\Requests\StoreLeaseRequest;
use App\Models\Lease;
use App\Models\Room;
use App\Models\Tenant;
use App\Services\HouseholdService;
use App\Services\LeaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Leases Module: contractual tenancy agreements (signed physical MOA reference details),
 * unit assignments, security deposit breakdowns, utility sub-entities, and lifecycle states.
 */
class LeaseController extends Controller
{
    public function __construct(private LeaseService $leases) {}

    public function index(Request $request): View
    {
        $status = $request->input('status', 'Active');
        if (! in_array($status, ['Active', 'Moving out', 'Moved out', 'Ended', 'Cancelled', 'All'], true)) {
            $status = 'Active';
        }

        $search = trim((string) $request->input('search', ''));
        $roomType = $request->input('room_type', 'All');

        $query = Lease::with(['room', 'tenant', 'moveout'])
            ->withCount('activeOccupants');

        if ($status === 'Moving out') {
            $query->displayStatus('Moving out');
        } elseif ($status === 'Moved out') {
            $query->displayStatus('Moved out');
        } elseif ($status === 'Active' && $request->has('status')) {
            $query->displayStatus('Active');
        } elseif ($status !== 'All') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $s = '%'.$search.'%';
            $query->where(function ($q) use ($s) {
                $q->whereHas('room', fn ($rq) => $rq->where('room_no', 'like', $s))
                    ->orWhereHas('tenant', fn ($tq) => $tq->where('full_name', 'like', $s));
            });
        }

        if ($roomType !== 'All' && in_array($roomType, Room::TYPES, true)) {
            $query->whereHas('room', fn ($rq) => $rq->where('room_type', $roomType));
        }

        $leases = $query->orderByDesc('move_in_date')->get();

        $counts = [
            'Active' => Lease::where('status', 'Active')->count(),
            'Ended' => Lease::where('status', 'Ended')->count(),
            'Cancelled' => Lease::where('status', 'Cancelled')->count(),
            'All' => Lease::count(),
        ];

        return view('leases.index', [
            'leases' => $leases,
            'status' => $status,
            'search' => $search,
            'roomType' => $roomType,
            'counts' => $counts,
            'vacantRoomsCount' => Room::where('status', 'Vacant')->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $rooms = Room::where('status', 'Vacant')->orderBy('room_no')->get();
        $selected = $rooms->firstWhere('room_id', $request->integer('room_id'));

        $inquiryId = $request->integer('inquiry_id') ?: null;
        $inquiry = $inquiryId ? \App\Models\Inquiry::find($inquiryId) : null;
        if ($inquiry && ! $selected && $inquiry->room_id) {
            $selected = $rooms->firstWhere('room_id', $inquiry->room_id);
        }

        $selectedTenantId = $request->integer('tenant_id') ?: null;
        $selectedTenant = $selectedTenantId ? Tenant::find($selectedTenantId) : null;

        $tenantToForm = $selectedTenant;
        if (! $tenantToForm && $inquiry) {
            $tenantToForm = new Tenant([
                'full_name' => $inquiry->prospect_name,
                'contact_no' => $inquiry->contact_no,
                'status' => 'Active',
            ]);
        }

        // Tenants eligible to lease (no active lease and strictly neither Blacklisted nor Archived).
        $eligibleTenants = Tenant::whereNotIn('status', ['Blacklisted', 'Archived'])
            ->whereNull('deleted_at')
            ->whereDoesntHave('leases', fn ($q) => $q->where('status', 'Active'))
            ->orderBy('full_name')->get();

        $todayDay = today()->day;
        $defaultDueDay = collect(Lease::STAGGERED_DUE_DAYS)->sortBy(fn ($d) => abs($d - $todayDay))->first();

        return view('leases.create', [
            'rooms' => $rooms,
            'selectedRoomId' => $selected?->room_id,
            'tenant' => $tenantToForm ?? new Tenant(['status' => 'Active']),
            'selectedTenantId' => $selectedTenantId,
            'inquiryId' => $inquiryId,
            'inquiry' => $inquiry,
            'eligibleTenants' => $eligibleTenants,
            'staggeredDueDays' => Lease::STAGGERED_DUE_DAYS,
            'lease' => new Lease([
                'moa_date' => today(),
                'move_in_date' => today(),
                'due_day' => $defaultDueDay,
                'min_stay_months' => Lease::DEFAULT_MIN_STAY_MONTHS,
                'monthly_rent' => $selected?->monthly_rent,
                'advance_amount' => $selected?->monthly_rent,
            ]),
        ]);
    }

    public function store(StoreLeaseRequest $request): RedirectResponse
    {
        $lease = $this->leases->create(
            $request->tenantData(),
            $request->leaseData(),
            $request->validated('co_tenant_name'),
            $request->user(),
            $request->moveInReading(),
            $request->inquiryId(),
            $request->paymentData(),
            $request->applianceName(),
        );

        return redirect()->route('leases.show', $lease)
            ->with('status', "MOA saved. Room {$lease->room->room_no} is now Occupied.")
            ->with('warning', $this->depositWarning($lease));
    }

    public function show(Lease $lease, HouseholdService $household, Request $request): View
    {
        $lease->load([
            'tenant',
            'room',
            'user',
            'occupants' => fn ($q) => $q->orderByDesc('is_principal')->orderBy('date_added'),
            'appliances' => fn ($q) => $q->orderByDesc('date_added'),
            'visitors' => fn ($q) => $q->orderByDesc('arrival_date'),
            'bills' => fn ($q) => $q->orderByDesc('due_date'),
            'moveout',
        ]);

        return view('leases.show', [
            'lease' => $lease,
            'household' => $household->summary($lease, $request->user()),
            'canCancel' => Gate::allows('cancel', $lease),
            'maxOccupants' => min($lease->room->max_occupants, Room::MAX_OCCUPANTS),
        ]);
    }

    public function edit(Lease $lease): View
    {
        return view('leases.edit', [
            'lease' => $lease->load(['tenant', 'room']),
            'staggeredDueDays' => Lease::STAGGERED_DUE_DAYS,
        ]);
    }

    public function update(LeaseRequest $request, Lease $lease): RedirectResponse
    {
        $this->leases->update($lease, $request->leaseData(), $request->user());

        return redirect()->route('leases.show', $lease)
            ->with('status', 'MOA details were updated.')
            ->with('warning', $this->depositWarning($lease));
    }

    public function cancel(Lease $lease, Request $request): RedirectResponse
    {
        Gate::authorize('cancel', $lease);
        $this->leases->cancel($lease, $request->user());

        return redirect()->route('leases.show', $lease)
            ->with('status', "The lease was cancelled. Room {$lease->room->room_no} is Vacant again.");
    }

    private function depositWarning(Lease $lease): ?string
    {
        return $this->leases->depositWarning(
            (float) $lease->monthly_rent,
            (float) $lease->deposit_maintenance,
            (float) $lease->deposit_damage,
            (float) $lease->deposit_bills,
            (float) $lease->deposit_keys,
        );
    }
}
