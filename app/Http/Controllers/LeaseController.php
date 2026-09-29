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

/** Encode, view, correct and cancel MOAs (Owner/Manager only; see routes). */
class LeaseController extends Controller
{
    public function __construct(private LeaseService $leases) {}

    public function create(Request $request): View
    {
        $rooms = Room::where('status', 'Vacant')->orderBy('room_no')->get();
        $selected = $rooms->firstWhere('room_id', $request->integer('room_id'));

        return view('leases.create', [
            'rooms' => $rooms,
            'selectedRoomId' => $selected?->room_id,
            'tenant' => new Tenant,
            // Returning tenants: anyone without an active lease.
            'returningTenants' => Tenant::whereDoesntHave('leases', fn ($q) => $q->where('status', 'Active'))
                ->orderBy('full_name')->get(),
            'lease' => new Lease([
                'moa_date' => today(),
                'move_in_date' => today(),
                'due_day' => today()->day,
                'min_stay_months' => Lease::DEFAULT_MIN_STAY_MONTHS,
                // Opened from a room's "Encode MOA" button: start from that room's rent.
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
        );

        return redirect()->route('leases.show', $lease)
            ->with('status', "MOA saved. Room {$lease->room->room_no} is now Occupied.")
            ->with('warning', $this->depositWarning($lease));
    }

    public function show(Lease $lease, HouseholdService $household, Request $request): View
    {
        $lease->load(['tenant', 'room', 'user', 'occupants' => fn ($q) => $q->orderByDesc('is_principal')->orderBy('date_added')]);

        return view('leases.show', [
            'lease' => $lease,
            'household' => $household->summary($lease, $request->user()),
            'canCancel' => Gate::allows('cancel', $lease),
            'maxOccupants' => min($lease->room->max_occupants, Room::MAX_OCCUPANTS),
        ]);
    }

    public function edit(Lease $lease): View
    {
        return view('leases.edit', ['lease' => $lease->load(['tenant', 'room'])]);
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
            (float) $lease->monthly_rent, (float) $lease->deposit_maintenance, (float) $lease->deposit_damage,
            (float) $lease->deposit_bills, (float) $lease->deposit_keys,
        );
    }
}
