<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\Gate;

/**
 * Data for the "Appliances & visitors" panels, used by every role.
 * It deliberately contains NO rent, deposit, advance, MOA dates, tenant contact/ID or co-tenant details,
 * because the Caretaker sees these panels (decisions 10 and 12).
 */
class HouseholdService
{
    public function __construct(private VisitorService $visitors) {}

    public function summary(Lease $lease, User $viewer): array
    {
        $lease->loadMissing(['room', 'tenant', 'appliances', 'visitors', 'occupants']);

        $coTenant = $viewer->hasRole('Owner', 'Manager')
            ? $lease->occupants->firstWhere('is_principal', false)
            : null;

        return [
            'lease_id' => $lease->lease_id,
            'lease_active' => $lease->status === 'Active',
            'can_manage' => Gate::forUser($viewer)->allows('manageHousehold', $lease),
            'room_no' => $lease->room->room_no,
            'room_metered' => $lease->room->is_metered,
            'tenant_name' => $lease->tenant->full_name,
            'co_tenant_name' => $coTenant?->full_name,
            'appliance_fee' => (float) Setting::current()->appliance_fee,
            // Current appliances first, then removed ones (newest first).
            'appliances' => $lease->appliances
                ->sortBy([['date_removed', 'asc'], ['date_added', 'desc']])
                ->values(),
            'visitors' => $lease->visitors
                ->sortByDesc('arrival_date')
                ->values()
                ->map(fn (Visitor $v) => [
                    'visitor' => $v,
                    'days' => $this->visitors->stayDays($v),
                    'over' => $this->visitors->isOverStay($v),
                ]),
        ];
    }
}
