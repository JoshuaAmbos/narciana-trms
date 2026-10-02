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
        return view('rooms.create', [
            'room' => new Room(['room_type' => 'Basic', 'is_metered' => false, 'max_occupants' => Room::MAX_OCCUPANTS]),
            'types' => Room::TYPES,
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
        return view('rooms.edit', ['room' => $room, 'types' => Room::TYPES]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        DB::transaction(function () use ($request, $room) {
            $room->update($request->validated()); // status is not in the validated data, so it never changes here
            AuditLog::record('Room changed', 'tbl_room', $room->room_id);
        });

        return redirect()->route('rooms.index')->with('status', "Room {$room->room_no} was updated.");
    }
}
