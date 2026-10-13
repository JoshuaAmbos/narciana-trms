<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/** Settings (Owner only): rates and rules in tbl_setting. */
class SettingController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'setting' => Setting::current()->load('updatedBy'),
            'bases' => Setting::PENALTY_BASES,
        ]);
    }

    public function update(SettingRequest $request, SettingService $settings): RedirectResponse
    {
        $changes = $settings->update($request->validated(), $request->user());

        // Clear config/route cache if active to ensure scheduler re-evaluates
        Artisan::call('optimize:clear');

        return redirect()->route('settings.index')->with('status', $changes
            ? 'Settings saved. New bills will use the new values: '.implode('; ', $changes).'.'
            : 'No changes to save.');
    }
}
