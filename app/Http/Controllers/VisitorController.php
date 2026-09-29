<?php

namespace App\Http\Controllers;

use App\Http\Requests\EndDateRequest;
use App\Http\Requests\HouseholdEntryRequest;
use App\Models\Lease;
use App\Models\Visitor;
use App\Services\VisitorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/** Visitor log with the MOA two-day rule (all roles). */
class VisitorController extends Controller
{
    public function store(HouseholdEntryRequest $request, Lease $lease, VisitorService $rule): RedirectResponse
    {
        Gate::authorize('manageHousehold', $lease);

        $visitor = Visitor::create($request->validated() + ['lease_id' => $lease->lease_id]);

        return back()->with('status', "Visitor {$visitor->visitor_name} was logged.")
            ->with('warning', $this->overStayWarning($visitor, $rule));
    }

    public function depart(EndDateRequest $request, Visitor $visitor, VisitorService $rule): RedirectResponse
    {
        Gate::authorize('manageHousehold', $visitor->lease);

        $visitor->update(['departure_date' => $request->validated('departure_date')]);

        return back()->with('status', "Departure of {$visitor->visitor_name} was recorded.")
            ->with('warning', $this->overStayWarning($visitor, $rule));
    }

    private function overStayWarning(Visitor $visitor, VisitorService $rule): ?string
    {
        return $rule->isOverStay($visitor)
            ? "{$visitor->visitor_name} stayed {$rule->stayDays($visitor)} days, more than the 2 days allowed by the MOA."
            : null;
    }
}
