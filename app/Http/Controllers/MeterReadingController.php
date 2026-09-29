<?php

namespace App\Http\Controllers;

use App\Models\MeterReading;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Meter reading history of rooms with their own meter (Owner/Manager only; see routes). */
class MeterReadingController extends Controller
{
    public function index(Request $request): View
    {
        $rooms = Room::where('is_metered', true)->orderBy('room_no')->get();
        $roomId = $request->integer('room') ?: null;

        $readings = MeterReading::with(['room', 'user', 'bills'])
            ->whereIn('room_id', $rooms->pluck('room_id'))
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->orderByDesc('reading_date')->orderByDesc('reading_id')
            ->paginate(30)->withQueryString();

        return view('meter-readings.index', ['rooms' => $rooms, 'roomId' => $roomId, 'readings' => $readings]);
    }
}
