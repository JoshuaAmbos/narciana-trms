<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Room;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Rooms Module: room availability, specs, rates, and waiting inquiries.
 * Adding/editing is restricted to the Owner.
 */
class RoomController extends Controller
{
    public function index(Request $request): View
    {
        $query = Room::with('concerns');

        // Search by room number
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('room_no', 'like', "%{$search}%");
        }

        // Filter by room status
        $status = $request->filled('status') ? (string) $request->input('status') : 'All';
        if ($status !== 'All' && in_array($status, Room::STATUSES, true)) {
            $query->where('status', $status);
        }

        // Filter by room type
        $type = $request->filled('type') ? (string) $request->input('type') : 'All';
        if ($type !== 'All' && in_array($type, ['Basic', 'Aircon'], true)) {
            $query->where('room_type', $type);
        }

        // Demand from queued waiting inquiries for each room type (or 'Any')
        $waitingDemands = [
            'Aircon' => Inquiry::where('status', 'Waiting')->whereIn('preferred_type', ['Aircon', 'Any'])->count(),
            'Basic' => Inquiry::where('status', 'Waiting')->whereIn('preferred_type', ['Basic', 'Any'])->count(),
        ];

        // Paginate results (default: 8 per page) and keep query strings
        $rooms = $query->orderBy('room_no', 'asc')
            ->paginate(8)
            ->withQueryString();

        $rooms->each(function (Room $room) use ($waitingDemands) {
            $room->waiting_demand = $waitingDemands[$room->room_type] ?? 0;
        });

        // Status counts for filter chips
        $statusCounts = [
            'All' => Room::count(),
            'Vacant' => Room::where('status', 'Vacant')->count(),
            'Occupied' => Room::where('status', 'Occupied')->count(),
        ];

        return view('rooms.index', compact(
            'rooms',
            'search',
            'status',
            'type',
            'statusCounts',
            'waitingDemands'
        ) + [
            'statuses' => ['All', 'Vacant', 'Occupied'],
            'types' => ['All', 'Basic', 'Aircon'],
        ]);
    }

    public function create(): View
    {
        $setting = Setting::current();

        return view('rooms.create', [
            'room' => new Room([
                'room_type' => 'Basic',
                'monthly_rent' => $setting->standard_basic_rent,
                'is_metered' => false,
                'max_occupants' => Room::MAX_OCCUPANTS,
            ]),
            'types' => Room::TYPES,
            'setting' => $setting,
        ]);
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $room = DB::transaction(function () use ($request) {
            $room = Room::create($request->validated() + [
                'status' => 'Vacant',
                'expected_vacant_date' => null,
            ]);
            AuditLog::record('Room created', 'tbl_room', $room->room_id);

            return $room;
        });

        return redirect()->route('rooms.index')->with('status', "Room {$room->room_no} was added.");
    }

    public function edit(Room $room): View
    {
        return view('rooms.edit', [
            'room' => $room,
            'types' => Room::TYPES,
            'setting' => Setting::current(),
        ]);
    }

    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        DB::transaction(function () use ($request, $room) {
            $oldRent = (float) $room->monthly_rent;
            $room->update($request->validated()); // status is not in the validated data, so it never changes here
            $newRent = (float) $room->monthly_rent;
            $detail = null;
            if ($oldRent !== $newRent) {
                $detail = 'Monthly rent: ₱'.number_format($oldRent, 2).' → ₱'.number_format($newRent, 2);
            }
            AuditLog::record('Room changed', 'tbl_room', $room->room_id, null, $detail);
        });

        return redirect()->route('rooms.index')->with('status', "Room {$room->room_no} was updated.");
    }
}
