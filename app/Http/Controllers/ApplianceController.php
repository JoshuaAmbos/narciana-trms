<?php

namespace App\Http\Controllers;

use App\Http\Requests\EndDateRequest;
use App\Http\Requests\HouseholdEntryRequest;
use App\Models\Appliance;
use App\Models\Lease;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Appliance register for rooms WITHOUT their own meter (all roles). */
class ApplianceController extends Controller
{
    public function store(HouseholdEntryRequest $request, Lease $lease): RedirectResponse
    {
        Gate::authorize('manageHousehold', $lease);

        if ($lease->room->is_metered) {
            throw ValidationException::withMessages([
                'appliance_name' => "Room {$lease->room->room_no} has its own meter, so appliances are not charged (electricity is billed per kWh).",
            ])->errorBag('appliance');
        }

        $appliance = Appliance::create($request->validated() + [
            'lease_id' => $lease->lease_id,
            'monthly_fee' => Setting::current()->appliance_fee, // flat fee from Settings, never typed by staff
            'user_id' => $request->user()->user_id,
        ]);

        return back()->with('status', "{$appliance->appliance_name} was added (₱".number_format((float) $appliance->monthly_fee, 2).' a month).');
    }

    public function remove(EndDateRequest $request, Appliance $appliance): RedirectResponse
    {
        Gate::authorize('manageHousehold', $appliance->lease);

        if ($appliance->date_removed === null) {
            $appliance->update(['date_removed' => $request->validated('date_removed')]);
        }

        return back()->with('status', "{$appliance->appliance_name} is marked as removed.");
    }
}
