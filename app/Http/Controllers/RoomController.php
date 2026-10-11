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
        if ($status === 'Disabled') {
            $query->where('is_rentable', false);
        } elseif ($status === 'Vacant') {
            $query->where('status', 'Vacant')->where('is_rentable', true);
        } elseif ($status === 'Occupied') {
            $query->where('status', 'Occupied');
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

        $rooms = $query->orderBy('room_no', 'asc')->get();

        $rooms->each(function (Room $room) use ($waitingDemands) {
            $room->waiting_demand = $waitingDemands[$room->room_type] ?? 0;
        });

        // Status counts for filter chips
        $statusCounts = [
            'All' => Room::count(),
            'Vacant' => Room::where('status', 'Vacant')->where('is_rentable', true)->count(),
            'Occupied' => Room::where('status', 'Occupied')->count(),
            'Disabled' => Room::where('is_rentable', false)->count(),
        ];

        $setting = Setting::current();

        $roomsMap = $rooms->keyBy('room_id')->map(function (Room $r) {
            return [
                'room_id' => $r->room_id,
                'room_no' => $r->room_no,
                'room_type' => $r->room_type,
                'is_metered' => (bool) $r->is_metered,
                'max_occupants' => (int) $r->max_occupants,
                'monthly_rent' => (float) $r->monthly_rent,
                'monthly_rent_formatted' => number_format((float) $r->monthly_rent, 2, '.', ''),
                'status' => $r->status,
                'is_rentable' => (bool) ($r->is_rentable ?? true),
                'disabled_reason' => $r->disabled_reason ?? '',
                'update_url' => route('rooms.update', $r),
                'toggle_url' => route('rooms.toggle-rentable', $r),
            ];
        });

        return view('rooms.index', compact(
            'rooms',
            'search',
            'status',
            'type',
            'statusCounts',
            'waitingDemands',
            'setting',
            'roomsMap'
        ) + [
            'statuses' => ['All', 'Vacant', 'Occupied', 'Disabled'],
            'types' => ['All', 'Basic', 'Aircon'],
        ]);
    }

    public function checkAvailability(Request $request): \Illuminate\Http\JsonResponse
    {
        $roomNo = trim((string) $request->input('room_no', ''));
        $excludeId = $request->input('exclude_id');

        if ($roomNo === '') {
            return response()->json([
                'available' => false,
                'exists' => false,
                'message' => 'Room number is required.',
            ]);
        }

        if (! ctype_digit($roomNo)) {
            return response()->json([
                'available' => false,
                'exists' => false,
                'room_no' => $roomNo,
                'message' => 'The room number must be a number.',
            ]);
        }

        $query = Room::whereRaw('LOWER(TRIM(room_no)) = ?', [strtolower($roomNo)]);
        if ($excludeId) {
            $query->where('room_id', '!=', $excludeId);
        }

        $exists = $query->exists();

        return response()->json([
            'available' => ! $exists,
            'exists' => $exists,
            'room_no' => $roomNo,
            'message' => $exists
                ? "Room \"{$roomNo}\" already exists. Please choose a different room number."
                : "✓ Room \"{$roomNo}\" is available",
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
                'max_occupants' => $setting->default_room_capacity ?? Room::MAX_OCCUPANTS,
                'is_rentable' => true,
            ]),
            'types' => Room::TYPES,
            'setting' => $setting,
        ]);
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $room = DB::transaction(function () use ($request) {
            $data = $request->validated();
            if (! isset($data['max_occupants'])) {
                $data['max_occupants'] = Setting::current()->default_room_capacity ?? 2;
            }
            $room = Room::create($data + [
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
            $oldRentable = (bool) ($room->is_rentable ?? true);
            $room->update($request->validated()); // status is not in the validated data, so it never changes here
            $newRent = (float) $room->monthly_rent;
            $newRentable = (bool) ($room->is_rentable ?? true);
            $detail = null;
            if ($oldRent !== $newRent) {
                $detail = 'Monthly rent: ₱'.number_format($oldRent, 2).' → ₱'.number_format($newRent, 2);
            }
            if ($oldRentable !== $newRentable) {
                $rentableMsg = $newRentable ? 'Room enabled for renting' : 'Room disabled for renting';
                $detail = $detail ? $detail.'; '.$rentableMsg : $rentableMsg;
            }
            AuditLog::record('Room changed', 'tbl_room', $room->room_id, null, $detail);
        });

        return redirect()->route('rooms.index')->with('status', "Room {$room->room_no} was updated.");
    }

    public function toggleRentable(Request $request, Room $room): RedirectResponse
    {
        \Illuminate\Support\Facades\Gate::authorize('update', $room);

        if ($room->status === 'Occupied') {
            return back()->withErrors(['room' => "Room {$room->room_no} is currently occupied and cannot be disabled."]);
        }

        $newVal = ! $room->isRentable();
        $reason = $newVal ? null : ($request->input('disabled_reason') ?: 'Out of service / maintenance');

        DB::transaction(function () use ($room, $newVal, $reason) {
            $room->update([
                'is_rentable' => $newVal,
                'disabled_reason' => $reason,
            ]);

            AuditLog::record(
                'Room changed',
                'tbl_room',
                $room->room_id,
                null,
                $newVal ? 'Room marked available for renting' : 'Room disabled for renting'
            );
        });

        $msg = $newVal
            ? "Room {$room->room_no} is now available for renting."
            : "Room {$room->room_no} has been disabled for renting.";

        return back()->with('status', $msg);
    }
}
