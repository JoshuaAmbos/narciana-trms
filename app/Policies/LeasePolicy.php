<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;
use App\Services\LeaseService;

/**
 * Record-level rules for leases. Route middleware already limits MOA pages to Owner/Manager;
 * appliances and visitors are open to every role (decision 10).
 */
class LeasePolicy
{
    /** View any leases in directory: Owner/Manager only. */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['Owner', 'Manager']);
    }

    /** View specific lease MOA details: Owner/Manager only. */
    public function view(User $user, Lease $lease): bool
    {
        return in_array($user->role, ['Owner', 'Manager']);
    }

    /** Create new lease MOA: Owner/Manager only. */
    public function create(User $user): bool
    {
        return in_array($user->role, ['Owner', 'Manager']);
    }

    /** Update lease MOA: Owner/Manager only. */
    public function update(User $user, Lease $lease): bool
    {
        return in_array($user->role, ['Owner', 'Manager']);
    }

    /** Appliances & visitors page. The Caretaker only sees rooms that are currently leased. */
    public function viewHousehold(User $user, Lease $lease): bool
    {
        return $user->hasRole('Owner', 'Manager') || $lease->status === 'Active';
    }

    /** Adding/removing appliances and visitors: only on an active lease. */
    public function manageHousehold(User $user, Lease $lease): bool
    {
        return $lease->status === 'Active';
    }

    /** Cancel an MOA encoded by mistake: Owner/Manager, and only while it has no bills or payments. */
    public function cancel(User $user, Lease $lease): bool
    {
        return in_array($user->role, ['Owner', 'Manager']) && app(LeaseService::class)->canCancel($lease);
    }

    /** End a lease: Owner/Manager only. */
    public function endLease(User $user, Lease $lease): bool
    {
        return in_array($user->role, ['Owner', 'Manager']);
    }
}
