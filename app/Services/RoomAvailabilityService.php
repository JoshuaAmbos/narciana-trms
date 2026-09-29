<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Room;
use Illuminate\Support\Collection;

/**
 * Matches waiting prospects (inquiries) to rooms that are Vacant or Vacating.
 *
 * A prospect fits a room when:
 *  - the room is Vacant or Vacating,
 *  - the inquiry is Waiting,
 *  - the preferred type is the room's type, or "Any",
 *  - the number of occupants is not more than the room's maximum.
 * Prospects are listed oldest inquiry first.
 */
class RoomAvailabilityService
{
    public function fits(Room $room, Inquiry $inquiry): bool
    {
        return $room->isAvailable()
            && $inquiry->status === 'Waiting'
            && in_array($inquiry->preferred_type, [$room->room_type, 'Any'], true)
            && $inquiry->no_of_occupants <= $room->max_occupants;
    }

    /**
     * @param  Collection<int, Room>  $rooms
     * @param  Collection<int, Inquiry>  $inquiries
     * @return array<int, Collection<int, Inquiry>>  room_id => fitting prospects (oldest first), only for available rooms
     */
    public function match(Collection $rooms, Collection $inquiries): array
    {
        $ordered = $this->oldestFirst($inquiries);
        $matches = [];

        foreach ($rooms as $room) {
            if ($room->isAvailable()) {
                $matches[$room->room_id] = $ordered->filter(fn (Inquiry $i) => $this->fits($room, $i))->values();
            }
        }

        return $matches;
    }

    /** All Waiting inquiries, oldest first (same inquiry date: the one logged first). */
    public function waitingProspects(): Collection
    {
        return $this->oldestFirst(Inquiry::where('status', 'Waiting')->get());
    }

    /** @return array<int, Collection<int, Inquiry>> */
    public function matchesForAllRooms(Collection $rooms): array
    {
        return $this->match($rooms, $this->waitingProspects());
    }

    private function oldestFirst(Collection $inquiries): Collection
    {
        return $inquiries->sortBy([
            fn (Inquiry $a, Inquiry $b) => $a->inquiry_date <=> $b->inquiry_date,
            fn (Inquiry $a, Inquiry $b) => $a->inquiry_id <=> $b->inquiry_id,
        ])->values();
    }
}
