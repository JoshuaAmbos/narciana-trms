<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantRequest;
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

        $query = Tenant::with(['activeLease.room', 'leases' => fn ($q) => $q->orderByDesc('move_in_date')]);

        if ($search !== '') {
            $s = '%'.$search.'%';
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', $s)
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
            $query->whereDoesntHave('leases', fn ($q) => $q->where('status', 'Active'));
        }

        $tenants = $query->orderBy('full_name')->get();

        $metrics = [
            'total' => Tenant::count(),
            'active_renters' => Tenant::whereHas('leases', fn ($q) => $q->where('status', 'Active'))->count(),
            'past_renters' => Tenant::whereDoesntHave('leases', fn ($q) => $q->where('status', 'Active'))->count(),
            'blacklisted' => Tenant::where('status', 'Blacklisted')->count(),
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

    public function create(): View
    {
        return view('tenants.create', [
            'tenant' => new Tenant(['status' => 'Active']),
            'idTypes' => Tenant::ID_TYPES,
        ]);
    }

    public function store(TenantRequest $request): RedirectResponse
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

    public function update(TenantRequest $request, Tenant $tenant): RedirectResponse
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
}
