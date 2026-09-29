<?php

namespace Database\Seeders;

use App\Models\Appliance;
use App\Models\Concern;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Services\BillingService;
use App\Services\ConcernService;
use App\Services\LeaseService;
use App\Services\PaymentService;
use App\Services\PenaltyService;
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
 * Module 5: move-in meter readings for metered rooms and every bill already due (through BillingService).
 * Module 6: payments (Cash and GCash, some late with a penalty), a few unpaid and Overdue bills.
 * Module 7: concerns (open, in progress, resolved, repeated; one charge billed, one waiting to be picked).
 * Module 8 adds move-outs here.
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
        $this->seedConcerns($owner);
        $bills = $this->seedBills($owner);
        $paid = $this->seedPayments($owner);

        $this->command?->info("Demo data ready: 13 rooms (10 occupied, 3 vacant), 10 sample tenants, sample inquiries, {$bills} past bills ({$paid} paid).");
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
                $room->is_metered ? 1000.0 + 250 * $n : null, // move-in meter reading (kWh)
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

    /**
     * Concerns through ConcernService: open (one urgent), in progress (a shared area), resolved, a repeated issue,
     * one charge already billed (door knob) and one charge not billed yet (stained wall).
     */
    private function seedConcerns(User $owner): void
    {
        $service = app(ConcernService::class);
        $manager = User::where('role', 'Manager')->firstOrFail();
        $caretaker = User::where('role', 'Caretaker')->firstOrFail();
        $at = fn (int $daysAgo, string $time) => today()->subDays($daysAgo)->setTimeFromTimeString($time);
        $leaseOf = fn (string $roomNo) => Lease::whereHas('room', fn ($q) => $q->where('room_no', $roomNo))->where('status', 'Active')->first();
        $roomId = fn (string $roomNo) => Room::where('room_no', $roomNo)->value('room_id');

        $samples = [
            // [room, category, priority, channel, description, assigned, status, reported, resolved, resolution, cost, charged]
            ['209', 'Professional repair', 'Urgent', 'Group chat', 'Tripping breaker', $manager, 'Open', $at(1, '21:40'), null, null, null, false],
            ['209', 'Professional repair', 'Routine', 'Verbal', 'Tripping breaker when the aircon and kettle run together', $manager, 'Resolved', $at(80, '19:10'), $at(79, '10:00'), 'Electrician tightened the breaker terminals', 850.00, false],
            [null, 'Shared area', 'Routine', 'Text', 'Clogged drain in the 2nd-floor CR', $caretaker, 'In progress', $at(2, '07:15'), null, null, null, false],
            ['203', 'Maintenance', 'Routine', 'Verbal', 'Busted bulb (vacant room)', $caretaker, 'Open', $at(3, '18:02'), null, null, null, false],
            ['210', 'Disturbance', 'Routine', 'Group chat', 'Noise after 10 PM', $manager, 'Resolved', $at(9, '23:30'), $at(8, '09:00'), 'Talked to the tenant; quiet hours reminded', null, false],
            ['202', 'Damage', 'Routine', 'Verbal', 'Broken door knob', $caretaker, 'Resolved', $at(52, '08:20'), $at(50, '15:00'), 'Door knob replaced', 350.00, true],
            ['204', 'Damage', 'Routine', 'Verbal', 'Stained wall near the bed', $caretaker, 'Resolved', $at(6, '10:00'), $at(5, '16:00'), 'Wall cleaned and repainted', 600.00, true],
            ['211', 'Billing', 'Routine', 'Text', 'Asked why the water charge is for 1 occupant', $manager, 'Resolved', $at(12, '12:00'), $at(12, '15:30'), 'Explained: water is per occupant staying in the period', null, false],
        ];

        foreach ($samples as [$roomNo, $category, $priority, $channel, $text, $assigned, $status, $reported, $resolved, $resolution, $cost, $charged]) {
            $service->create([
                'room_id' => $roomNo ? $roomId($roomNo) : null,
                'lease_id' => $roomNo ? $leaseOf($roomNo)?->lease_id : null,
                'reported_at' => $reported, 'channel' => $channel, 'category' => $category, 'priority' => $priority,
                'description' => $text, 'assigned_to' => $assigned->user_id, 'status' => $status,
                'resolved_at' => $resolved, 'resolution' => $resolution, 'cost' => $cost, 'charged_to_tenant' => $charged,
            ], $owner); // logged as the Owner so cost and charge are kept
        }
    }

    /**
     * Every bill already due (due date today or earlier), generated through BillingService in order.
     * Bills due in the next few days are left for the "To bill" list. kWh used follows a fixed formula.
     */
    private function seedBills(User $owner): int
    {
        $billing = app(BillingService::class);
        $count = 0;

        foreach (Lease::with('room', 'tenant')->orderBy('lease_id')->get() as $lease) {
            $n = (int) preg_replace('/\D/', '', $lease->tenant->full_name); // Sample Tenant n
            $month = 0;

            while (($due = $billing->nextDueDate($lease))->lte(today())) {
                $reading = null;
                if ($lease->room->is_metered) {
                    $previous = (float) $billing->lastReading($lease->room_id)->current_reading;
                    $kwh = 60 + 7 * $n + (9 * $month) % 40;
                    $reading = [
                        'previous_reading' => $previous,
                        'current_reading' => $previous + $kwh,
                        'reading_date' => $due->copy()->subDay()->toDateString(),
                        'source' => 'Meter',
                    ];
                }
                // Charged concerns resolved before this due date are picked (as the Manager would tick them).
                $charges = app(ConcernService::class)->chargeable($lease)
                    ->filter(fn (Concern $c) => $c->resolved_at->lt($due))->pluck('concern_id')->all();

                $billing->generate($lease, $reading, $due->toDateString(), $owner, null, $charges);
                $count++;
                $month++;
            }
        }

        return $count;
    }

    /**
     * Payments through PaymentService (oldest bill first, exact amounts, ACK numbers):
     *  - odd Sample Tenant numbers pay Cash (received by the Manager), even numbers GCash (received by the Owner);
     *  - every 4th payment is made 2 days after the grace period, so it carries a penalty;
     *  - Sample Tenants 1 and 6 leave their last 2 bills unpaid (the older one is always Overdue);
     *  - everyone else leaves only a newest bill that is still within its due date + grace unpaid.
     */
    private function seedPayments(User $owner): int
    {
        $payments = app(PaymentService::class);
        $penalties = app(PenaltyService::class);
        $manager = User::where('role', 'Manager')->firstOrFail();
        $grace = (int) Setting::current()->grace_days;
        $count = 0;

        foreach (Lease::with('tenant')->orderBy('lease_id')->get() as $lease) {
            $n = (int) preg_replace('/\D/', '', $lease->tenant->full_name);
            $bills = $lease->bills()->orderBy('due_date')->get();
            $leaveUnpaid = in_array($n, [1, 6], true) ? 2 : 0;
            $last = $bills->last();
            if ($leaveUnpaid === 0 && $last && $last->due_date->copy()->addDays($grace)->gte(today())) {
                $leaveUnpaid = 1; // newest bill not overdue yet
            }

            foreach ($bills->slice(0, max(0, $bills->count() - $leaveUnpaid))->values() as $k => $bill) {
                $late = ($n + $k) % 4 === 0;
                $date = $late ? $bill->due_date->copy()->addDays($grace + 2) : $bill->due_date->copy()->subDay();
                $date = $date->min(today());

                $gcash = $n % 2 === 0;
                $payments->record($bill, [
                    'payment_date' => $date->toDateString(),
                    'mode' => $gcash ? 'GCash' : 'Cash',
                    'gcash_ref_no' => $gcash ? '9'.str_pad((string) ($n * 100 + $k), 12, '0', STR_PAD_LEFT) : null,
                    'amount' => $penalties->forBill($bill, $date)['total'],
                ], $gcash ? $owner : $manager);
                $count++;
            }
        }

        $payments->refreshOverdue();

        return $count;
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
