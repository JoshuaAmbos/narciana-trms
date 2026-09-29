<?php

namespace Database\Seeders;

use App\Models\Inquiry;
use App\Models\Room;
use App\Models\User;
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

        $this->seedInquiries();
    }

    /** A few fictional prospects in a mix of types and outcomes. */
    private function seedInquiries(): void
    {
        $owner = User::where('role', 'Owner')->first();
        if (! $owner) {
            $this->command?->warn('No Owner account yet: run "php artisan db:seed" first to add sample inquiries.');

            return;
        }
        if (Inquiry::where('prospect_name', 'like', 'Sample Prospect %')->exists()) {
            $this->command?->line('Sample inquiries already exist, skipped.');

            return;
        }

        $samples = [
            // [days ago, type wanted, occupants, channel, representative, outcome, room_no]
            [12, 'Aircon', 2, 'Walk-in', false, 'Waiting', null],
            [9, 'Any', 1, 'Call', true, 'Waiting', null],
            [7, 'Basic', 1, 'Walk-in', false, 'Rented', '203'],
            [5, 'Aircon', 1, 'Call', false, 'Referred', null],
            [3, 'Basic', 3, 'Walk-in', false, 'Declined', null],
            [1, 'Basic', 2, 'Call', false, 'Waiting', null],
        ];

        foreach ($samples as $i => [$daysAgo, $type, $occupants, $channel, $viaRep, $status, $roomNo]) {
            Inquiry::create([
                'prospect_name' => 'Sample Prospect '.($i + 1),
                'contact_no' => '0917 000 '.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'preferred_type' => $type,
                'no_of_occupants' => $occupants,
                'channel' => $channel,
                'via_representative' => $viaRep,
                'inquiry_date' => today()->subDays($daysAgo),
                'status' => $status,
                'room_id' => $roomNo ? Room::where('room_no', $roomNo)->value('room_id') : null,
                'user_id' => $owner->user_id,
                'remarks' => $occupants > Room::MAX_OCCUPANTS ? 'Needs room for 3; declined (max 2 per room)' : null,
            ]);
        }

        $this->command?->info('Sample inquiries ready (test data).');
    }
}
