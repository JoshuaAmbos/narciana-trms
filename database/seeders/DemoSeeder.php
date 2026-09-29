<?php

namespace Database\Seeders;

use App\Models\Appliance;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Services\LeaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * SAMPLE DATA FOR LOCAL TESTING ONLY. Never run on the live hosting (it refuses to run in production).
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=DemoSeeder
 *
 * Gives the same data every time: 13 rooms (201-213), 10 occupied by "Sample Tenant 1-10" (encoded through
 * LeaseService so every rule applies), 3 vacant with matching waiting inquiries. Dates are fixed offsets from
 * today (e.g. "5 months ago on the 5th"), so due days never change; nothing is random.
 *
 * Later modules add sample meter readings, bills and payments here.
 */
class DemoSeeder extends Seeder
{
    /** room_no => [type, monthly rent, max occupants]. Aircon rooms have their own meter. */
    private const ROOMS = [
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
    ];

    /**
     * Tenant n => [room, months ago, day of month moved in, co-tenant?, appliances [name, source, days after move-in]].
     * Rooms 203, 205 (Basic) and 208 (Aircon) stay Vacant.
     */
    private const LEASES = [
        1 => ['201', 5, 5, false, [['Rice cooker', 'Declared', 0], ['Electric kettle', 'CCTV', 40]]],
        2 => ['202', 4, 12, true, [['Electric fan (extra)', 'Declared', 0]]],
        3 => ['204', 3, 20, false, [['Television', 'Declared', 10]]],
        4 => ['206', 2, 28, false, []],
        5 => ['207', 6, 1, true, []],
        6 => ['209', 4, 15, false, []],
        7 => ['210', 3, 25, true, []],
        8 => ['211', 1, 10, false, [['Rice cooker', 'Declared', 0], ['Laptop', 'CCTV', 5]]],
        9 => ['212', 2, 30, false, []],
        10 => ['213', 1, 3, false, [['Electric kettle', 'Declared', 0]]],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder is test data and never runs in production.');

            return;
        }

        $owner = User::where('role', 'Owner')->first();
        if (! $owner || ! Setting::exists()) {
            $this->command?->error('Run "php artisan migrate:fresh --seed" first (needs the Owner account and settings).');

            return;
        }
        if (Tenant::where('full_name', 'like', 'Sample Tenant %')->exists() || Lease::exists()) {
            $this->command?->warn('Demo data (or real leases) already exist. For a clean copy run: php artisan migrate:fresh --seed, then this seeder again.');

            return;
        }

        $this->seedRooms();
        $this->seedLeases($owner);
        $this->seedInquiries($owner);

        $this->command?->info('Demo data ready: 13 rooms (10 occupied, 3 vacant), 10 sample tenants, sample inquiries.');
    }

    private function seedRooms(): void
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
    }

    private function seedLeases(User $owner): void
    {
        $service = app(LeaseService::class);
        $fee = Setting::current()->appliance_fee;

        foreach (self::LEASES as $n => [$roomNo, $monthsAgo, $day, $withCoTenant, $appliances]) {
            $room = Room::where('room_no', $roomNo)->firstOrFail();
            $moveIn = $this->dayMonthsAgo($monthsAgo, $day);
            $rent = (float) $room->monthly_rent;

            $lease = $service->create(
                [
                    'full_name' => "Sample Tenant {$n}",
                    'contact_no' => '0918 000 '.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                    'valid_id_type' => $n % 2 ? 'Student ID' : "Driver's License",
                    'valid_id_no' => 'SAMPLE-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                ],
                ['room_id' => $room->room_id, 'moa_date' => $moveIn->copy()->subDay()->toDateString(),
                    'move_in_date' => $moveIn->toDateString(), 'due_day' => $moveIn->day, 'monthly_rent' => $rent,
                    'min_stay_months' => Lease::DEFAULT_MIN_STAY_MONTHS, 'advance_amount' => $rent] + $this->deposits($rent),
                $withCoTenant ? "Sample Co-tenant {$n}" : null,
                $owner,
            );

            foreach ($appliances as [$name, $source, $daysAfter]) {
                Appliance::create([
                    'lease_id' => $lease->lease_id, 'appliance_name' => $name, 'source' => $source,
                    'date_added' => $moveIn->copy()->addDays($daysAfter)->min(today()), 'monthly_fee' => $fee,
                    'user_id' => $owner->user_id,
                ]);
            }

            if ($n === 3) { // stayed 1 day and left: within the MOA rule
                Visitor::create(['lease_id' => $lease->lease_id, 'visitor_name' => 'Sample Visitor A', 'relationship' => 'Parent',
                    'arrival_date' => today()->subDays(9), 'departure_date' => today()->subDays(8)]);
            }
            if ($n === 6) { // arrived 4 days ago and still here: flagged (over 2 days)
                Visitor::create(['lease_id' => $lease->lease_id, 'visitor_name' => 'Sample Visitor B', 'relationship' => 'Sibling',
                    'arrival_date' => today()->subDays(4), 'departure_date' => null]);
            }
        }
    }

    private function seedInquiries(User $owner): void
    {
        $rooms = Room::pluck('room_id', 'room_no');
        $samples = [
            // [days ago, type wanted, occupants, channel, representative, outcome, room_no, remarks]
            [10, 'Aircon', 2, 'Walk-in', false, 'Waiting', null, null],          // fits 208
            [6, 'Any', 1, 'Call', true, 'Waiting', null, null],                  // fits 203, 205, 208
            [3, 'Basic', 2, 'Walk-in', false, 'Waiting', null, null],            // fits 203, 205
            [20, 'Aircon', 1, 'Call', false, 'Referred', null, 'Referred to a nearby boarding house'],
            [15, 'Basic', 3, 'Walk-in', false, 'Declined', null, 'Needs room for 3; declined (max 2 per room)'],
            [null, 'Basic', 1, 'Walk-in', false, 'Rented', '211', 'Became Sample Tenant 8'], // dated before that MOA
        ];

        foreach ($samples as $i => [$daysAgo, $type, $occupants, $channel, $viaRep, $status, $roomNo, $remarks]) {
            $date = $daysAgo !== null
                ? today()->subDays($daysAgo)
                : Lease::where('room_id', $rooms[$roomNo])->value('moa_date')->copy()->subDays(5);

            Inquiry::create([
                'prospect_name' => 'Sample Prospect '.($i + 1),
                'contact_no' => '0917 000 '.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'preferred_type' => $type,
                'no_of_occupants' => $occupants,
                'channel' => $channel,
                'via_representative' => $viaRep,
                'inquiry_date' => $date,
                'status' => $status,
                'room_id' => $roomNo ? $rooms[$roomNo] : null,
                'user_id' => $owner->user_id,
                'remarks' => $remarks,
            ]);
        }
    }

    /** One month's deposit split into the 4 parts; the parts always add up exactly to the rent. */
    private function deposits(float $rent): array
    {
        $maintenance = round($rent * 0.30, 2);
        $damage = round($rent * 0.40, 2);
        $bills = round($rent * 0.20, 2);

        return [
            'deposit_maintenance' => $maintenance,
            'deposit_damage' => $damage,
            'deposit_bills' => $bills,
            'deposit_keys' => round($rent - $maintenance - $damage - $bills, 2),
        ];
    }

    /** The given day, $months months ago (clamped to that month's last day), e.g. 5 months ago on the 5th. */
    private function dayMonthsAgo(int $months, int $day): Carbon
    {
        $month = today()->startOfMonth()->subMonthsNoOverflow($months);

        return $month->day(min($day, $month->daysInMonth));
    }
}
