<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Illuminate\View\View;

/**
 * Module 1: placeholder dashboard that shows the setup is working.
 * The real counters (bills due, overdue, concerns, vacant rooms) are built in Module 9.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'userCount' => User::count(),
            'roomCount' => Room::count(),
            'vacantCount' => Room::where('status', 'Vacant')->count(),
            'setting' => Setting::first(),
            'rooms' => Room::orderBy('room_no')->get(),
        ]);
    }
}
