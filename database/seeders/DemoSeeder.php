<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

/**
 * SAMPLE DATA FOR LOCAL TESTING ONLY. Do not run on the live hosting:
 * the real rooms and rents are entered by the Owner.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            // room_no => [type, monthly rent]
            '201' => ['Basic', 3000.00],
            '202' => ['Basic', 3000.00],
            '203' => ['Basic', 3000.00],
            '204' => ['Basic', 3500.00],
            '205' => ['Basic', 3000.00],
            '206' => ['Basic', 3000.00],
            '207' => ['Aircon', 3600.00],
            '208' => ['Aircon', 3600.00],
            '209' => ['Aircon', 3600.00],
            '210' => ['Aircon', 3600.00],
            '211' => ['Basic', 3000.00],
        ];

        foreach ($rooms as $roomNo => [$type, $rent]) {
            Room::updateOrCreate(['room_no' => $roomNo], [
                'room_type' => $type,
                'is_metered' => $type === 'Aircon',
                'max_occupants' => 2,
                'monthly_rent' => $rent,
                'status' => 'Vacant',
                'expected_vacant_date' => null,
            ]);
        }

        $this->command?->info('Sample rooms 201-211 ready (test data).');
    }
}
