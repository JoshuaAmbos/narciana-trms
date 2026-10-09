<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Lease;
use App\Models\Room;
use App\Services\ReportService;
use Illuminate\View\View;

/** Printable reports (Owner/Manager only; see routes). Each report is a plain GET page with its filters in the URL. */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(): View
    {
        return view('reports.index', [
            'month' => today()->startOfMonth(),
            'summary' => $this->reports->summary(today()->startOfMonth()),
        ]);
    }

    public function collections(ReportFilterRequest $request): View
    {
        $month = $request->month();

        return view('reports.collections', ['month' => $month] + $this->reports->collections($month));
    }

    public function due(): View
    {
        return view('reports.due', $this->reports->dueAndOverdue());
    }

    public function statement(ReportFilterRequest $request): View
    {
        $lease = $request->filled('lease') ? Lease::findOrFail($request->validated('lease')) : null;

        return view('reports.statement', [
            'leases' => $this->reports->statementLeases(),
            'from' => $request->from(),
            'to' => $request->to(),
            'report' => $lease ? $this->reports->statement($lease, $request->from(), $request->to()) : null,
        ]);
    }

    public function concerns(ReportFilterRequest $request): View
    {
        $room = $request->room();

        return view('reports.concerns', [
            'rooms' => Room::orderBy('room_no')->get(['room_id', 'room_no']),
            'room' => $room,
            'from' => $request->from(),
            'to' => $request->to(),
        ] + $this->reports->concernHistory($room, $request->from(), $request->to()));
    }

    public function inquiries(ReportFilterRequest $request): View
    {
        return view('reports.inquiries', $this->reports->inquiryDemand(
            $request->from(today()->startOfMonth()), $request->to(today()->endOfMonth()->startOfDay())));
    }
}
