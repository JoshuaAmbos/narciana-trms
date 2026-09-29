<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Services\HouseholdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** "Appliances & visitors" page for one lease (all roles; no MOA, rent or deposit data). */
class HouseholdController extends Controller
{
    public function show(Lease $lease, HouseholdService $household, Request $request): View
    {
        Gate::authorize('viewHousehold', $lease);

        return view('household.show', ['household' => $household->summary($lease, $request->user())]);
    }
}
