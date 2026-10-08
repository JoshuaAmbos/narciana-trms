<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingRequest;
use App\Models\Lease;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Settings (Owner only): rates and rules in tbl_setting, plus the fixed rules shown read-only. */
class SettingController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'setting' => Setting::current()->load('updatedBy'),
            'bases' => Setting::PENALTY_BASES,
            'fixedRules' => [
                ['Default due day', 'The tenant\'s move-in day of the month (snapped to staggered billing days: 10th, 15th, 20th, 25th, or 30th).'],
                ['Contractual minimum stay', Lease::DEFAULT_MIN_STAY_MONTHS.' months (mandatory lock-in period per MOA Clause 9).'],
                ['Logout after inactivity', config('session.lifetime').' minutes, with a warning 1 minute before (SESSION_LIFETIME in .env).'],
            ],
        ]);
    }

    public function update(SettingRequest $request, SettingService $settings): RedirectResponse
    {
        $changes = $settings->update($request->validated(), $request->user());

        return redirect()->route('settings.index')->with('status', $changes
            ? 'Settings saved. New bills will use the new values: '.implode('; ', $changes).'.'
            : 'No changes to save.');
    }
}
