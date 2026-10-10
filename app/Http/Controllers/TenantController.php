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

        $query = Tenant::query()
            ->with(['activeLease.room', 'leases' => fn ($q) => $q->orderByDesc('move_in_date')]);

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

        if ($status !== 'All' && in_array($status, Tenant::STATUSES, true)) {
            $query->where('status', $status);
        }

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

        $tenants = $query->orderBy('full_name')->get();

        $baseQuery = Tenant::query();

        $metrics = [
            'total' => (clone $baseQuery)->count(),
            'active_renters' => (clone $baseQuery)->whereHas('leases', fn ($q) => $q->where('status', 'Active'))->count(),
            'past_renters' => (clone $baseQuery)->whereDoesntHave('leases', fn ($q) => $q->where('status', 'Active'))
                ->where(function ($q) {
                    $q->whereHas('leases', fn ($lq) => $lq->whereIn('status', ['Ended', 'Cancelled']))
                        ->orWhereExists(function ($iq) {
                            $iq->selectRaw('1')->from('tbl_inquiry')
                                ->whereColumn('tbl_inquiry.contact_no', 'tbl_tenant.contact_no')
                                ->orWhereColumn('tbl_inquiry.prospect_name', 'tbl_tenant.full_name');
                        });
                })->count(),
            'blacklisted' => (clone $baseQuery)->where('status', 'Blacklisted')->count(),
        ];

        return view('tenants.index', [
            'tenants' => $tenants,
            'search' => $search,
            'status' => $status,
            'statuses' => ['All', ...Tenant::STATUSES],
            'tenancyFilter' => $tenancyFilter,
            'metrics' => $metrics,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('leases.create')
            ->with('info', 'New tenants are registered through the Lease / MOA encoding process.');
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = Tenant::create([
            ...$request->validated(),
            'status' => 'Active',
        ]);

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
            'leases' => fn ($q) => $q->with(['room', 'occupants'])->orderByDesc('move_in_date'),
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
        $tenant->update(collect($request->validated())->except(['return_to'])->all());

        // Keep the principal occupant's name in step with a corrected tenant name (active leases only).
        foreach ($tenant->leases()->where('status', 'Active')->get() as $lease) {
            $lease->principal()->update(['full_name' => $tenant->full_name]);
        }

        AuditLog::record('Tenant profile updated', 'tbl_tenant', $tenant->tenant_id, $request->user()->user_id);

        if ($request->filled('return_to')) {
            return redirect($request->input('return_to'))
                ->with('status', 'Tenant profile was updated.');
        }

        $lease = $tenant->leases()->where('status', 'Active')->first();

        return ($lease ? redirect()->route('leases.show', $lease) : redirect()->route('tenants.index'))
            ->with('status', 'Tenant profile was updated.');
    }
}
