<?php

namespace Tests\Unit;

use App\Models\Inquiry;
use App\Models\Room;
use App\Services\RoomAvailabilityService;
use Tests\TestCase;

/**
 * Hand-worked example (no database):
 *
 *   Rooms                                    Prospects (inquiries)
 *   201 Basic,  max 2, Vacant                #1 2026-09-01 Aircon, 2 occupants, Waiting
 *   208 Aircon, max 2, Vacant                #2 2026-09-03 Any,    1 occupant,  Waiting
 *   209 Aircon, max 1, Vacant                #3 2026-08-20 Basic,  1 occupant,  Waiting
 *   210 Aircon, max 2, Occupied              #4 2026-08-25 Aircon, 1 occupant,  Rented   (not waiting)
 *                                            #5 2026-09-02 Basic,  3 occupants, Waiting  (too many)
 *                                            #6 2026-09-01 Aircon, 1 occupant,  Waiting  (same day as #1, logged later)
 *
 *   Expected (oldest first):
 *   201 -> #3, #2          (Basic or Any, 1 fits max 2; #5 has 3 occupants)
 *   208 -> #1, #6, #2      (Aircon or Any; #1 before #6 because it was logged first on the same day)
 *   209 -> #6, #2          (max 1 occupant, so #1 with 2 does not fit)
 *   210 -> not listed      (Occupied)
 */
class RoomAvailabilityServiceTest extends TestCase
{
    private RoomAvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RoomAvailabilityService;
    }

    private function room(int $id, string $no, string $type, int $max, string $status): Room
    {
        $room = new Room(['room_no' => $no, 'room_type' => $type, 'max_occupants' => $max, 'status' => $status]);
        $room->room_id = $id;

        return $room;
    }

    private function inquiry(int $id, string $date, string $type, int $occupants, string $status = 'Waiting'): Inquiry
    {
        $inquiry = new Inquiry([
            'inquiry_date' => $date, 'preferred_type' => $type, 'no_of_occupants' => $occupants, 'status' => $status,
        ]);
        $inquiry->inquiry_id = $id;

        return $inquiry;
    }

    private function ids($collection): array
    {
        return $collection->pluck('inquiry_id')->all();
    }

    public function test_hand_worked_example(): void
    {
        $rooms = collect([
            $this->room(1, '201', 'Basic', 2, 'Vacant'),
            $this->room(2, '208', 'Aircon', 2, 'Vacant'),
            $this->room(3, '209', 'Aircon', 1, 'Vacant'),
            $this->room(4, '210', 'Aircon', 2, 'Occupied'),
        ]);
        $inquiries = collect([
            $this->inquiry(1, '2026-09-01', 'Aircon', 2),
            $this->inquiry(2, '2026-09-03', 'Any', 1),
            $this->inquiry(3, '2026-08-20', 'Basic', 1),
            $this->inquiry(4, '2026-08-25', 'Aircon', 1, 'Rented'),
            $this->inquiry(5, '2026-09-02', 'Basic', 3),
            $this->inquiry(6, '2026-09-01', 'Aircon', 1),
        ]);

        $matches = $this->service->match($rooms, $inquiries);

        $this->assertSame([1, 2, 3], array_keys($matches), 'Occupied room 210 must not be listed');
        $this->assertSame([3, 2], $this->ids($matches[1]));
        $this->assertSame([1, 6, 2], $this->ids($matches[2]));
        $this->assertSame([6, 2], $this->ids($matches[3]));
    }

    public function test_any_type_fits_both_room_types(): void
    {
        $prospect = $this->inquiry(1, '2026-09-01', 'Any', 1);

        $this->assertTrue($this->service->fits($this->room(1, '201', 'Basic', 2, 'Vacant'), $prospect));
        $this->assertTrue($this->service->fits($this->room(2, '208', 'Aircon', 2, 'Vacant'), $prospect));
    }

    public function test_wrong_type_does_not_fit(): void
    {
        $this->assertFalse($this->service->fits(
            $this->room(1, '201', 'Basic', 2, 'Vacant'),
            $this->inquiry(1, '2026-09-01', 'Aircon', 1),
        ));
    }

    public function test_only_waiting_prospects_fit(): void
    {
        $room = $this->room(1, '201', 'Basic', 2, 'Vacant');

        foreach (['Rented', 'Referred', 'Declined'] as $status) {
            $this->assertFalse($this->service->fits($room, $this->inquiry(1, '2026-09-01', 'Basic', 1, $status)));
        }
    }

    public function test_occupied_room_fits_nobody(): void
    {
        $this->assertFalse($this->service->fits(
            $this->room(1, '201', 'Basic', 2, 'Occupied'),
            $this->inquiry(1, '2026-09-01', 'Basic', 1),
        ));
    }

    public function test_exactly_the_room_maximum_fits_one_more_does_not(): void
    {
        $room = $this->room(1, '201', 'Basic', 2, 'Vacant');

        $this->assertTrue($this->service->fits($room, $this->inquiry(1, '2026-09-01', 'Basic', 2)));
        $this->assertFalse($this->service->fits($room, $this->inquiry(2, '2026-09-01', 'Basic', 3)));
    }
}
