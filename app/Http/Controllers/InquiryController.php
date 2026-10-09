<?php

namespace App\Http\Controllers;

use App\Http\Requests\InquiryRequest;
use App\Http\Requests\StoreInquiryRequest;
use App\Http\Requests\UpdateInquiryStatusRequest;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Inquiries Module: prospect intake, multi-channel leads, waiting queue,
 * and lifecycle outcomes (Waiting -> Rented / Referred / Declined).
 */
class InquiryController extends Controller
{
    public function index(Request $request, RoomAvailabilityService $availability): View
    {
        $filter = $request->validate(['status' => ['nullable', Rule::in(Inquiry::STATUSES)]]);
        $status = $filter['status'] ?? null;

        $counts = [
            'All' => Inquiry::count(),
            'Waiting' => Inquiry::where('status', 'Waiting')->count(),
            'Rented' => Inquiry::where('status', 'Rented')->count(),
            'Referred' => Inquiry::where('status', 'Referred')->count(),
            'Declined' => Inquiry::where('status', 'Declined')->count(),
        ];

        $query = Inquiry::with(['room', 'user'])
            ->when($status, fn ($q) => $q->where('status', $status));

        // Waiting queue is sorted chronologically (oldest first); others are newest first.
        if ($status === 'Waiting') {
            $query->orderBy('inquiry_date')->orderBy('inquiry_id');
        } else {
            $query->orderByDesc('inquiry_date')->orderByDesc('inquiry_id');
        }

        $inquiries = $query->paginate(25)->withQueryString();

        $rooms = Room::orderBy('room_no')->get();
        $vacantRooms = $rooms->where('status', 'Vacant');
        $hasVacantAircon = $vacantRooms->where('room_type', 'Aircon')->isNotEmpty();
        $hasVacantBasic = $vacantRooms->where('room_type', 'Basic')->isNotEmpty();

        return view('inquiries.index', [
            'inquiries' => $inquiries,
            'status' => $status,
            'counts' => $counts,
            'waitingCount' => $counts['Waiting'],
            'inquiry' => new Inquiry([
                'inquiry_date' => today(),
                'status' => 'Waiting',
                'no_of_occupants' => 1,
                'preferred_type' => 'Any',
                'channel' => 'Walk-in',
            ]),
            'rooms' => $rooms,
            'vacantRooms' => $vacantRooms,
            'hasVacantAircon' => $hasVacantAircon,
            'hasVacantBasic' => $hasVacantBasic,
            'holds' => $availability->rentalHolds(),
            'types' => Inquiry::TYPES,
            'channels' => Inquiry::CHANNELS,
            'statuses' => Inquiry::STATUSES,
        ]);
    }

    public function store(StoreInquiryRequest $request, RoomAvailabilityService $availability): RedirectResponse
    {
        $inquiry = Inquiry::create(array_merge($request->validated(), [
            'status' => 'Waiting',
            'room_id' => null,
            'user_id' => $request->user()->user_id,
        ]));

        AuditLog::record('Inquiry logged', 'tbl_inquiry', $inquiry->inquiry_id, $request->user()->user_id);

        return redirect()->route('inquiries.index')
            ->with('status', "Inquiry from {$inquiry->prospect_name} was saved ({$inquiry->status}).")
            ->with('warning', $this->typeWarning($inquiry, $availability));
    }

    public function edit(Inquiry $inquiry, RoomAvailabilityService $availability): View
    {
        return view('inquiries.edit', [
            'inquiry' => $inquiry->load('user'),
            'rooms' => Room::orderBy('room_no')->get(),
            'holds' => $availability->rentalHolds(exceptInquiryId: $inquiry->inquiry_id),
            'types' => Inquiry::TYPES,
            'channels' => Inquiry::CHANNELS,
            'statuses' => Inquiry::STATUSES,
        ]);
    }

    public function update(InquiryRequest $request, Inquiry $inquiry, RoomAvailabilityService $availability): RedirectResponse
    {
        $inquiry->update($request->validated()); // user_id (who logged it) stays unchanged

        AuditLog::record('Inquiry updated', 'tbl_inquiry', $inquiry->inquiry_id, $request->user()->user_id);

        return redirect()->route('inquiries.index')
            ->with('status', "Inquiry from {$inquiry->prospect_name} was updated ({$inquiry->status}).")
            ->with('warning', $this->typeWarning($inquiry, $availability));
    }

    public function updateStatus(UpdateInquiryStatusRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $oldStatus = $inquiry->status;
        $inquiry->update($request->validated());

        AuditLog::record(
            'Inquiry status updated',
            'tbl_inquiry',
            $inquiry->inquiry_id,
            $request->user()->user_id,
            "{$oldStatus} → {$inquiry->status}"
        );

        return redirect()->route('inquiries.index')
            ->with('status', "Inquiry from {$inquiry->prospect_name} is now {$inquiry->status}.");
    }

    /** Warn (do not block) when the chosen room is not the type the prospect asked for. */
    private function typeWarning(Inquiry $inquiry, RoomAvailabilityService $availability): ?string
    {
        $room = $inquiry->room_id ? Room::find($inquiry->room_id) : null;

        return $room ? $availability->typeMismatchWarning($room, $inquiry->preferred_type) : null;
    }
}
