<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Setting;
use Illuminate\View\View;

/**
 * Placeholder dashboard: rooms and the current rates.
 * The real counters (bills due, overdue, concerns, vacant rooms) are built in Module 9.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'vacantCount' => Room::where('status', 'Vacant')->count(),
            'setting' => Setting::first(),
            'rooms' => Room::orderBy('room_no')->get(),
        ]);
    }
}
