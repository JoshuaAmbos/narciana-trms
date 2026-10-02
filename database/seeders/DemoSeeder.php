<?php

namespace Database\Seeders;

use App\Models\Appliance;
use App\Models\Concern;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Moveout;
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
use App\Services\SettlementService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * SAMPLE DATA FOR LOCAL TESTING ONLY. Never run on the live hosting (it refuses to run in production).
 *
 *   php artisan migrate:fresh --seed
 *   php artisan db:seed --class=DemoSeeder
 *
 * Gives the same data every time: 17 rooms (201-217), 14 leases for "Sample Tenant 1-14" (encoded through
 * LeaseService so every rule applies), 3 vacant with matching waiting inquiries. Dates are fixed offsets from
 * today (e.g. "5 months ago on the 5th"), so due days never change; nothing is random.
 *
 * Module 5: move-in meter readings for metered rooms and every bill already due (through BillingService).
 * Module 6: payments (Cash and GCash, some late with a penalty), a few unpaid and Overdue bills.
 * Module 7: concerns (open, in progress, resolved, repeated; one charge billed, one waiting to be picked).
 * Module 8: move-outs: a notice only (206), one awaiting the Owner's approval (214), one settled with a refund (215).
 * Instructor review (decision 18): one move-out in every status: Pending (206 notice, 214 inspected), Refunded (216,
 * refund not handed over yet), Excess billed (217, damage above the deposit) and Settled (215); concerns in the new
 * categories with a timeline and sample photos.
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
        '214' => ['Aircon', 3700.00, 2],
        '215' => ['Basic', 3200.00, 2],
        '216' => ['Aircon', 3700.00, 2],
        '217' => ['Basic', 3200.00, 2],
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
        11 => ['214', 7, 8, true, []],                                  // moves out (awaiting approval)
        12 => ['215', 6, 20, false, [['Rice cooker', 'Declared', 0]]],  // moved out and settled
        13 => ['216', 7, 14, false, []],                                // moved out, refund approved (Refunded)
        14 => ['217', 5, 22, true, [['Rice cooker', 'Declared', 0], ['Flat iron', 'CCTV', 30]]], // damage above the deposit (Excess billed)
    ];

    /** Renter's Profile samples, picked by tenant number (no randomness). */
    private const PROVINCES = ['Davao del Norte', 'Davao de Oro', 'Cotabato', 'Bukidnon', 'Surigao del Sur'];

    private const OCCUPATIONS = ['Student', 'Call center agent', 'Sales clerk', 'Nurse', 'Office staff', 'Teacher'];

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
        $this->seedMoveoutNotices($owner);   // before billing: no regular bill after a move-out date
        $bills = $this->seedBills($owner);
        $paid = $this->seedPayments($owner);
        $this->seedSettlements($owner);      // final bills, inspection items, compute, approve

        $this->command?->info('Demo data ready: '.count(self::ROOMS).' rooms, '.count(self::LEASES)." sample tenants (5 move-outs, one per status), inquiries, concerns, {$bills} past bills ({$paid} paid).");
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
                    'provincial_address' => "Purok {$n}, Sample Barangay, ".self::PROVINCES[$n % count(self::PROVINCES)],
                    'occupation' => self::OCCUPATIONS[$n % count(self::OCCUPATIONS)],
                    'prev_building' => $n % 3 ? "Sample Apartment {$n}" : null, // optional on the form
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
                'wants_contact' => $status === 'Waiting' && $i < 2, // the two oldest waiting prospects asked to be called
                'inquiry_date' => $date,
                'status' => $status,
                'room_id' => $roomNo ? $rooms[$roomNo] : null,
                'user_id' => $owner->user_id,
                'remarks' => $remarks,
            ]);
        }
    }

    /**
     * Concerns through ConcernService, one per common boarding-house problem (decision 18). Each step happens "at" its
     * own past time, so the timeline shows who reported, who started and who resolved it, and when:
     * open (one urgent), in progress (one in a shared area), resolved, a repeated issue in room 209,
     * one charge already billed (door knob) and one charge not billed yet (stained wall).
     * Two concerns carry sample photos (plain grey pictures, labelled as samples).
     */
    private function seedConcerns(User $owner): void
    {
        $service = app(ConcernService::class);
        $manager = User::where('role', 'Manager')->firstOrFail();
        $caretaker = User::where('role', 'Caretaker')->firstOrFail();
        $at = fn (?int $daysAgo, string $time = '09:00') => $daysAgo === null ? null : today()->subDays($daysAgo)->setTimeFromTimeString($time);
        $leaseOf = fn (string $roomNo) => Lease::whereHas('room', fn ($q) => $q->where('room_no', $roomNo))->where('status', 'Active')->first();
        $roomId = fn (string $roomNo) => Room::where('room_no', $roomNo)->value('room_id');

        $samples = [
            // [room (null = shared area), category, priority, channel, description, logged by, assigned, status,
            //  reported, started, resolved (days ago, time), resolution, cost, charged, photos]
            ['209', 'Electrical', 'Urgent', 'Group chat', 'Circuit breaker trips whenever the aircon is turned on', $caretaker, $manager, 'Open',
                $at(1, '21:40'), null, null, null, null, false, ['reported']],
            ['209', 'Electrical', 'Routine', 'Verbal', 'Breaker tripped when the aircon and kettle ran together', $manager, $manager, 'Resolved',
                $at(80, '19:10'), $at(80, '20:00'), $at(79, '10:00'), 'Electrician tightened the breaker terminals; reminded the tenant not to run both together', 850.00, false, []],
            [null, 'Plumbing', 'Routine', 'Text', 'Clogged drain in the 2nd-floor shared CR', $caretaker, $caretaker, 'In progress',
                $at(2, '07:15'), $at(1, '08:30'), null, null, null, false, []],
            ['207', 'Aircon', 'Routine', 'Text', 'Aircon not cooling; the filter looks dusty', $manager, $caretaker, 'In progress',
                $at(4, '13:00'), $at(3, '10:00'), null, null, null, false, []],
            ['203', 'Lights', 'Routine', 'Verbal', 'Busted bulb (vacant room)', $caretaker, $caretaker, 'Open',
                $at(3, '18:02'), null, null, null, null, false, []],
            [null, 'Pest control', 'Routine', 'Verbal', 'Cockroaches near the shared kitchen sink', $caretaker, $caretaker, 'Open',
                $at(5, '19:30'), null, null, null, null, false, []],
            ['213', 'Other', 'Routine', 'Verbal', 'Asked for an extra wall shelf', $caretaker, $manager, 'Open',
                $at(6, '17:00'), null, null, null, null, false, []],
            ['212', 'Water supply', 'Urgent', 'Group chat', 'No water in the morning', $manager, $caretaker, 'Resolved',
                $at(20, '06:10'), $at(20, '06:30'), $at(20, '08:00'), 'Water pump reset and its switch replaced', null, false, []],
            ['201', 'Plumbing', 'Routine', 'Verbal', 'Leaking faucet in the room sink', $caretaker, $caretaker, 'Resolved',
                $at(30, '10:00'), $at(29, '09:00'), $at(29, '11:00'), 'Faucet washer replaced', 150.00, false, []],
            ['210', 'Noise/Disturbance', 'Routine', 'Group chat', 'Noise after 10 PM', $manager, $manager, 'Resolved',
                $at(9, '23:30'), null, $at(8, '09:00'), 'Talked to the tenant; quiet hours reminded', null, false, []],
            [null, 'Cleanliness (shared areas)', 'Routine', 'Text', 'Garbage not collected on the stairs landing', $caretaker, $caretaker, 'Resolved',
                $at(15, '07:00'), $at(15, '07:30'), $at(15, '09:00'), 'Garbage taken out; collection schedule posted', null, false, []],
            ['202', 'Door/Lock/Keys', 'Routine', 'Verbal', 'Broken door knob', $caretaker, $caretaker, 'Resolved',
                $at(52, '08:20'), $at(51, '09:00'), $at(50, '15:00'), 'Door knob replaced', 350.00, true, ['reported', 'resolved']],
            ['204', 'Damage to room or furniture', 'Routine', 'Verbal', 'Stained wall near the bed', $caretaker, $caretaker, 'Resolved',
                $at(6, '10:00'), $at(6, '14:00'), $at(5, '16:00'), 'Wall cleaned and repainted', 600.00, true, []],
            ['211', 'Billing concern', 'Routine', 'Text', 'Asked why the water charge is for 1 occupant', $manager, $manager, 'Resolved',
                $at(12, '12:00'), null, $at(12, '15:30'), 'Explained: water is per occupant staying in the period', null, false, []],
        ];

        try {
            foreach ($samples as [$roomNo, $category, $priority, $channel, $text, $loggedBy, $assigned, $status,
                $reported, $started, $resolved, $resolution, $cost, $charged, $photos]) {
                // 1. Reported (logged by the Caretaker or Manager).
                Carbon::setTestNow($reported);
                $concern = $service->create([
                    'room_id' => $roomNo ? $roomId($roomNo) : null,
                    'lease_id' => $roomNo ? $leaseOf($roomNo)?->lease_id : null,
                    'reported_at' => $reported, 'channel' => $channel, 'category' => $category, 'priority' => $priority,
                    'description' => $text, 'assigned_to' => $assigned->user_id, 'status' => 'Open',
                ], $loggedBy, ['photo' => in_array('reported', $photos, true) ? $this->samplePhoto('Sample photo: '.$category) : null]);

                // 2. In progress (by the assigned user).
                if ($started) {
                    Carbon::setTestNow($started);
                    $service->update($concern, ['status' => 'In progress'], $assigned);
                }

                // 3. Resolved (by the assigned user); a cost is set by the Owner/Manager.
                if ($status === 'Resolved') {
                    Carbon::setTestNow($resolved);
                    $service->update($concern, ['status' => 'Resolved', 'resolution' => $resolution], $assigned,
                        ['resolution_photo' => in_array('resolved', $photos, true) ? $this->samplePhoto('Sample photo: fixed') : null]);
                    if ($cost !== null) {
                        $service->update($concern, ['cost' => $cost, 'charged_to_tenant' => $charged], $manager);
                    }
                }
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    /** A plain grey 800x600 JPEG with a label: stands in for a tablet photo (no real pictures in sample data). */
    private function samplePhoto(string $label): UploadedFile
    {
        $image = imagecreatetruecolor(800, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 205, 212));
        imagerectangle($image, 20, 20, 779, 579, imagecolorallocate($image, 120, 128, 140));
        imagestring($image, 5, 40, 280, $label, imagecolorallocate($image, 40, 48, 60));
        $path = tempnam(sys_get_temp_dir(), 'nrc').'.jpg';
        imagejpeg($image, $path, 80);
        imagedestroy($image);

        return new UploadedFile($path, 'sample.jpg', 'image/jpeg', null, true);
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

            $actual = $lease->moveout?->actual_date;
            while (($due = $billing->nextDueDate($lease))->lte(today()) && ($actual === null || $due->lte($actual))) {
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
            // Sample Tenant 13 paid every bill before leaving, so the settlement ends in a refund (any day of the month).
            if ($leaveUnpaid === 0 && $n !== 13 && $last && $last->due_date->copy()->addDays($grace)->gte(today())) {
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

    /** Notices and move-out dates (before billing, so no regular bill is made after a move-out). */
    private function seedMoveoutNotices(User $owner): void
    {
        $settlements = app(SettlementService::class);
        $leaseOf = fn (string $roomNo) => Lease::whereHas('room', fn ($q) => $q->where('room_no', $roomNo))->firstOrFail();

        // [room, notice (days ago), planned (days from today, negative = past), moved out (days ago) or null]
        foreach ([['206', 5, 14, null], ['214', 35, -3, 3], ['215', 50, -20, 20], ['216', 40, -10, 10], ['217', 45, -6, 6]] as [$roomNo, $noticeAgo, $plannedIn, $actualAgo]) {
            $moveout = $settlements->giveNotice($leaseOf($roomNo), today()->subDays($noticeAgo)->toDateString(),
                today()->addDays($plannedIn)->toDateString(), $owner);
            if ($actualAgo !== null) {
                $settlements->updateDates($moveout, [
                    'notice_date' => $moveout->notice_date->toDateString(),
                    'planned_date' => $moveout->planned_date->toDateString(),
                    'actual_date' => today()->subDays($actualAgo)->toDateString(),
                ], $owner);
            }
        }
    }

    /**
     * Final bills, inspections and computed settlements. 214 waits for the Owner; 215 is approved and its refund handed
     * over (Settled); 216 is approved with the refund still to hand over (Refunded); 217's damage is more than the
     * deposit, so an excess bill is waiting for payment (Excess billed).
     */
    private function seedSettlements(User $owner): void
    {
        $settlements = app(SettlementService::class);
        $billing = app(BillingService::class);
        $items = [
            '214' => [['Wall paint scratches near the bed', 650.00], ['Missing key copy', 250.00]],
            '215' => [['Adhesive hook marks (MOA item 12)', 500.00]],
            '216' => [['Aircon remote control missing', 300.00]],
            '217' => [['Broken bed frame (replaced)', 2200.00], ['Lost door key and knob set', 450.00]],
        ];

        foreach (Moveout::with('lease.room')->whereNotNull('actual_date')->get() as $moveout) {
            $room = $moveout->lease->room;
            $reading = null;
            if ($room->is_metered) {
                $previous = (float) $billing->lastReading($room->room_id)->current_reading;
                $reading = ['previous_reading' => $previous, 'current_reading' => $previous + 45,
                    'reading_date' => $moveout->actual_date->toDateString(), 'source' => 'Meter'];
            }
            if ($billing->finalPeriod($moveout->lease, $moveout->actual_date)) {
                $billing->generateFinal($moveout, $reading, [], $owner);
            }
            foreach ($items[$room->room_no] ?? [] as [$text, $cost]) {
                $settlements->addItem($moveout, $text, $cost);
            }
            $settlements->markInspected($moveout, $owner);
            $settlements->compute($moveout, $owner);

            if (in_array($room->room_no, ['215', '216', '217'], true)) {
                $moveout = $settlements->approve($moveout, $owner);
                if ($room->room_no === '215' && $moveout->status === 'Refunded') {
                    $settlements->markRefundReleased($moveout, $owner);
                }
            }
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
