<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

/**
 * Official property room inventory and preset rates schedule for Narciana House.
 * Total 17 rooms (201-217).
 */
class RoomSeeder extends Seeder
{
    /** room_no => [type, monthly rent, max occupants]. */
    public const ROOMS = [
        '201' => ['Basic', 3000.00, 2],
        '202' => ['Basic', 3000.00, 2],
        '203' => ['Basic', 3000.00, 2],
        '204' => ['Basic', 3500.00, 2],
        '205' => ['Basic', 3000.00, 2],
        '206' => ['Basic', 3000.00, 2],
        '207' => ['Aircon', 3600.00, 2],
        '208' => ['Aircon', 3600.00, 2],
        '209' => ['Aircon', 3600.00, 2],
        '210' => ['Aircon', 3600.00, 2],
        '211' => ['Basic', 3000.00, 2],
        '212' => ['Aircon', 3800.00, 2],
        '213' => ['Basic', 2500.00, 1],
        '214' => ['Aircon', 3700.00, 2],
        '215' => ['Basic', 3200.00, 2],
        '216' => ['Aircon', 3700.00, 2],
        '217' => ['Basic', 3200.00, 2],
    ];

    public function run(): void
    {
        foreach (self::ROOMS as $roomNo => [$type, $rent, $max]) {
            Room::firstOrCreate(['room_no' => $roomNo], [
                'room_type' => $type,
                'is_metered' => $type === 'Aircon',
                'max_occupants' => $max,
                'monthly_rent' => $rent,
                'status' => 'Vacant',
                'expected_vacant_date' => null,
            ]);
        }

        $this->command?->info('Seeded official room inventory: '.count(self::ROOMS).' rooms.');
    }
}
