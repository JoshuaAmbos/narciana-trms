<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\AuditLog;
use App\Models\Lease;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tenants Module: master identity records, contact numbers, valid government ID verification,
 * and tenancy history for individuals.
 */
class TenantController extends Controller
{
    public function index(Request $request): View
    {
        // Caretaker: limited view showing only room number and tenant name for property maintenance.
        if ($request->user()->hasRole('Caretaker')) {
            $rows = Lease::with(['room', 'tenant'])->where('status', 'Active')->get()
                ->map(fn (Lease $l) => ['lease_id' => $l->lease_id, 'room_no' => $l->room->room_no, 'tenant_name' => $l->tenant->full_name])
                ->sortBy('room_no')->values();

            return view('tenants.caretaker', ['rows' => $rows]);
        }

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'All');
        $tenancyFilter = $request->input('tenancy', 'all');

        $isArchivedView = $request->boolean('archived');

        if ($isArchivedView) {
            $query = Tenant::onlyTrashed()->with(['leases' => fn ($q) => $q->orderByDesc('move_in_date')]);
        } else {
            $query = Tenant::withoutTrashed()->where('status', '!=', 'Archived')
                ->with(['activeLease.room', 'leases' => fn ($q) => $q->orderByDesc('move_in_date')]);
        }

        if ($search !== '') {
            $s = '%'.$search.'%';
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', $s)
                    ->orWhere('first_name', 'like', $s)
                    ->orWhere('last_name', 'like', $s)
                    ->orWhere('contact_no', 'like', $s)
                    ->orWhere('valid_id_no', 'like', $s)
                    ->orWhere('provincial_address', 'like', $s)
                    ->orWhere('occupation', 'like', $s)
                    ->orWhereHas('activeLease.room', fn ($rq) => $rq->where('room_no', 'like', $s));
            });
        }

        if (! $isArchivedView && $status !== 'All' && in_array($status, Tenant::ACTIVE_STATUSES, true)) {
            $query->where('status', $status);
        }

        if (! $isArchivedView) {
            if ($tenancyFilter === 'active') {
                $query->whereHas('leases', fn ($q) => $q->where('status', 'Active'));
            } elseif ($tenancyFilter === 'past') {
                $query->whereDoesntHave('leases', fn ($q) => $q->where('status', 'Active'))
                    ->where(function ($q) {
                        $q->whereHas('leases', fn ($lq) => $lq->whereIn('status', ['Ended', 'Cancelled']))
                            ->orWhereExists(function ($iq) {
                                $iq->selectRaw('1')->from('tbl_inquiry')
                                    ->whereColumn('tbl_inquiry.contact_no', 'tbl_tenant.contact_no')
                                    ->orWhereColumn('tbl_inquiry.prospect_name', 'tbl_tenant.full_name');
                            });
                    });
            }
        }

        $tenants = $query->orderBy('full_name')->get();

        $baseActiveQuery = Tenant::withoutTrashed()->where('status', '!=', 'Archived');

        $metrics = [
            'total' => (clone $baseActiveQuery)->count(),
            'active_renters' => (clone $baseActiveQuery)->whereHas('leases', fn ($q) => $q->where('status', 'Active'))->count(),
            'past_renters' => (clone $baseActiveQuery)->whereDoesntHave('leases', fn ($q) => $q->where('status', 'Active'))
                ->where(function ($q) {
                    $q->whereHas('leases', fn ($lq) => $lq->whereIn('status', ['Ended', 'Cancelled']))
                        ->orWhereExists(function ($iq) {
                            $iq->selectRaw('1')->from('tbl_inquiry')
                                ->whereColumn('tbl_inquiry.contact_no', 'tbl_tenant.contact_no')
                                ->orWhereColumn('tbl_inquiry.prospect_name', 'tbl_tenant.full_name');
                        });
                })->count(),
            'blacklisted' => (clone $baseActiveQuery)->where('status', 'Blacklisted')->count(),
            'archived' => Tenant::onlyTrashed()->orWhere('status', 'Archived')->count(),
        ];

        return view('tenants.index', [
            'tenants' => $tenants,
            'search' => $search,
            'status' => $status,
            'statuses' => ['All', ...Tenant::ACTIVE_STATUSES],
            'tenancyFilter' => $tenancyFilter,
            'metrics' => $metrics,
            'isArchivedView' => $isArchivedView,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('leases.create')
            ->with('info', 'New tenants are registered through the Lease / MOA encoding process.');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = Tenant::create($request->validated());

        AuditLog::record('Tenant registered', 'tbl_tenant', $tenant->tenant_id, $request->user()->user_id);

        return redirect()->route('tenants.index')
            ->with('status', "Tenant {$tenant->full_name} was registered successfully.")
            ->with('prompt_lease', true)
            ->with('new_tenant_id', $tenant->tenant_id)
            ->with('new_tenant_name', $tenant->full_name);
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load([
            'leases' => fn ($q) => $q->with(['room', 'occupants', 'moveout'])->orderByDesc('move_in_date'),
            'activeLease.room',
        ]);

        return view('tenants.show', [
            'tenant' => $tenant,
            'activeLease' => $tenant->activeLease,
            'leases' => $tenant->leases,
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        return view('tenants.edit', [
            'tenant' => $tenant,
            'activeLease' => $tenant->activeLease,
            'idTypes' => Tenant::ID_TYPES,
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->update($request->validated());

        // Keep the principal occupant's name in step with a corrected tenant name (active leases only).
        foreach ($tenant->leases()->where('status', 'Active')->get() as $lease) {
            $lease->principal()->update(['full_name' => $tenant->full_name]);
        }

        AuditLog::record('Tenant profile updated', 'tbl_tenant', $tenant->tenant_id, $request->user()->user_id);

        $lease = $tenant->leases()->where('status', 'Active')->first();

        return ($lease ? redirect()->route('leases.show', $lease) : redirect()->route('tenants.show', $tenant))
            ->with('status', 'Tenant profile was updated.');
    }

    public function archive(Request $request, Tenant $tenant): RedirectResponse
    {
        if ($tenant->hasActiveLease()) {
            return back()->with('error', 'Cannot archive a tenant with an active ongoing lease. End or settle the lease first.');
        }

        $tenant->update(['status' => 'Archived']);
        $tenant->delete();
        AuditLog::record('Tenant archived', 'tbl_tenant', $tenant->tenant_id, $request->user()->user_id);

        return redirect()->route('tenants.index')
            ->with('status', "Tenant {$tenant->full_name} has been moved to the archives.");
    }

    public function restore(Request $request, Tenant $tenant): RedirectResponse
    {
        $tenant->restore();
        $tenant->update(['status' => 'Inactive']);
        AuditLog::record('Tenant restored', 'tbl_tenant', $tenant->tenant_id, $request->user()->user_id);

        return redirect()->route('tenants.index', ['archived' => 1])
            ->with('status', "Tenant {$tenant->full_name} has been restored to inactive status.");
    }
}
