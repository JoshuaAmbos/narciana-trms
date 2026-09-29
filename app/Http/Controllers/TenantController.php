<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantRequest;
use App\Models\Lease;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** "Tenants & MOA" list (all roles, different view for the Caretaker) and tenant profile edits (Owner/Manager). */
class TenantController extends Controller
{
    public function index(Request $request): View
    {
        // Caretaker: only room number and principal tenant name of current leases (decision 12).
        if ($request->user()->hasRole('Caretaker')) {
            $rows = Lease::with(['room', 'tenant'])->where('status', 'Active')->get()
                ->map(fn (Lease $l) => ['lease_id' => $l->lease_id, 'room_no' => $l->room->room_no, 'tenant_name' => $l->tenant->full_name])
                ->sortBy('room_no')->values();

            return view('tenants.caretaker', ['rows' => $rows]);
        }

        $filter = $request->validate(['status' => ['nullable', Rule::in(Lease::STATUSES)]]);
        $status = $filter['status'] ?? 'Active';

        $leases = Lease::with(['room', 'tenant'])
            ->withCount('activeOccupants')
            ->where('status', $status)
            ->get()
            ->sortBy(fn (Lease $l) => $l->room->room_no)
            ->values();

        return view('tenants.index', ['leases' => $leases, 'status' => $status, 'statuses' => Lease::STATUSES]);
    }

    public function edit(Tenant $tenant): View
    {
        return view('tenants.edit', ['tenant' => $tenant, 'activeLease' => $tenant->leases()->where('status', 'Active')->first()]);
    }

    public function update(TenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        // Keep the principal occupant's name in step with a corrected tenant name (active leases only).
        foreach ($tenant->leases()->where('status', 'Active')->get() as $lease) {
            $lease->principal()->update(['full_name' => $tenant->full_name]);
        }

        $lease = $tenant->leases()->where('status', 'Active')->first();

        return ($lease ? redirect()->route('leases.show', $lease) : redirect()->route('tenants.index'))
            ->with('status', 'Tenant profile was updated.');
    }
}
