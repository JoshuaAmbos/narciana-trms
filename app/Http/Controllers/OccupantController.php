<?php

namespace App\Http\Controllers;

use App\Http\Requests\EndDateRequest;
use App\Http\Requests\HouseholdEntryRequest;
use App\Models\Lease;
use App\Models\Occupant;
use App\Services\LeaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/** Declared occupants of a lease (Owner/Manager only; see routes). */
class OccupantController extends Controller
{
    public function __construct(private LeaseService $leases) {}

    public function store(HouseholdEntryRequest $request, Lease $lease): RedirectResponse
    {
        try {
            $occupant = $this->leases->addOccupant($lease, $request->validated('full_name'), $request->validated('date_added'));
        } catch (ValidationException $e) {
            throw $e->errorBag('occupant');
        }

        return redirect()->route('leases.show', $lease)->with('status', "{$occupant->full_name} was added as an occupant.");
    }

    public function remove(EndDateRequest $request, Occupant $occupant): RedirectResponse
    {
        try {
            $this->leases->removeOccupant($occupant, $request->validated('date_removed'));
        } catch (ValidationException $e) {
            throw $e->errorBag('occupant');
        }

        return redirect()->route('leases.show', $occupant->lease_id)
            ->with('status', "{$occupant->full_name} is marked as no longer staying.");
    }
}
