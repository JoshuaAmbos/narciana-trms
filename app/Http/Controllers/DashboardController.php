<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard. Owner/Manager: bills due and overdue, open concerns, vacant rooms, refunds due.
 * Caretaker: open concerns and rooms only (DashboardService::forCaretaker loads no bills or amounts).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): View
    {
        $staff = $request->user()->hasRole('Owner', 'Manager');

        return view('dashboard', ['staff' => $staff] + ($staff ? $dashboard->forStaff() : $dashboard->forCaretaker()));
    }
}
