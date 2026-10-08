<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoomRequest;
use App\Models\AuditLog;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Rooms: the availability list (all roles) and adding/editing rooms (Owner). Inquiries have their own module. */
class RoomController extends Controller
{
    public function index(): View
    {
        return view('rooms.index', ['rooms' => Room::orderBy('room_no')->get()]);
    }

    public function create(): View
    {
        $setting = \App\Models\Setting::current();

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

    public function store(RoomRequest $request): RedirectResponse
    {
        $room = DB::transaction(function () use ($request) {
            $room = Room::create($request->validated() + ['status' => 'Vacant', 'expected_vacant_date' => null]);
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
            'setting' => \App\Models\Setting::current(),
        ]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
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
