<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Room;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Matches waiting prospects (inquiries) to rooms that are Vacant.
 *
 * A prospect fits a room when:
 *  - the room is Vacant,
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
     * @return array<int, Collection<int, Inquiry>> room_id => fitting prospects (oldest first), only for available rooms
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

    // ---------- Marking an inquiry as Rented ----------

    /**
     * Rented inquiries that still "hold" their room: no lease has been encoded for that room
     * with an MOA date on or after the inquiry date. Once the MOA is encoded (Module 4) the hold ends by itself.
     *
     * @return Collection<int, Inquiry> room_id => oldest holding inquiry
     */
    public function rentalHolds(?int $roomId = null, ?int $exceptInquiryId = null): Collection
    {
        return Inquiry::where('status', 'Rented')
            ->whereNotNull('room_id')
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->when($exceptInquiryId, fn ($q) => $q->where('inquiry_id', '!=', $exceptInquiryId))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tbl_lease')
                ->whereColumn('tbl_lease.room_id', 'tbl_inquiry.room_id')
                ->whereColumn('tbl_lease.moa_date', '>=', 'tbl_inquiry.inquiry_date'))
            ->orderBy('inquiry_date')
            ->orderBy('inquiry_id')
            ->get()
            ->unique('room_id')
            ->keyBy('room_id');
    }

    /**
     * Why this inquiry cannot be marked Rented for this room (field => message); empty when it can.
     * $existing is the inquiry being edited (null when logging a new one).
     */
    public function rentedProblems(Room $room, int $occupants, ?Inquiry $existing = null): array
    {
        $problems = [];

        if ($occupants > $room->max_occupants) {
            $problems['no_of_occupants'] = "Room {$room->room_no} allows at most {$room->max_occupants} "
                .Str::plural('occupant', $room->max_occupants)."; this inquiry has {$occupants}.";
        }

        // Editing an inquiry that is already Rented for this same room: the room may be Occupied by now (its MOA).
        $unchanged = $existing && $existing->getOriginal('status') === 'Rented'
            && (int) $existing->getOriginal('room_id') === (int) $room->room_id;

        if (! $unchanged && ! $room->isAvailable()) {
            $problems['room_id'] = "Room {$room->room_no} is {$room->status}. Only Vacant rooms can be marked as rented.";
        } elseif ($hold = $this->rentalHolds($room->room_id, $existing?->inquiry_id)->first()) {
            $problems['room_id'] = "Room {$room->room_no} is already marked as rented to {$hold->prospect_name} "
                ."(inquiry of {$hold->inquiry_date->format('j M Y')}) and its MOA has not been encoded yet.";
        }

        return $problems;
    }

    /** A warning (not an error) when the chosen room is not the type the prospect asked for. */
    public function typeMismatchWarning(Room $room, string $preferredType): ?string
    {
        if ($preferredType === 'Any' || $preferredType === $room->room_type) {
            return null;
        }

        return "Note: Room {$room->room_no} is a {$room->room_type} room, but the prospect asked for {$preferredType}.";
    }

    private function oldestFirst(Collection $inquiries): Collection
    {
        return $inquiries->sortBy([
            fn (Inquiry $a, Inquiry $b) => $a->inquiry_date <=> $b->inquiry_date,
            fn (Inquiry $a, Inquiry $b) => $a->inquiry_id <=> $b->inquiry_id,
        ])->values();
    }
}
