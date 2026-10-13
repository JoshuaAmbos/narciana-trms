<?php

namespace Database\Seeders;

use App\Models\Appliance;
use App\Models\Bill;
use App\Models\Concern;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Occupant;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production-realistic database seeder for Narciana House Space Rental Management System.
 *
 * Provides a complete 15-room property schedule, active and past billing cycles,
 * utility readings, tenant concerns, prospect inquiries, and visitor logs anchored
 * relative to the current date.
 *
 * Crucial constraint: Does NOT touch or re-seed staff accounts (tbl_user).
 * Safe to execute via: php artisan db:seed --class=RealisticDataSeeder
 */
class RealisticDataSeeder extends Seeder
{
    /**
     * 15-room schedule: room_no => [type, monthly_rent, max_occupants, is_metered]
     */
    private const ROOM_SCHEDULE = [
        // Basic Rooms (Unmetered, is_metered = 0)
        '201' => ['Basic', 3000.00, 2, false],
        '202' => ['Basic', 3000.00, 2, false],
        '203' => ['Basic', 3000.00, 2, false],
        '204' => ['Basic', 3500.00, 2, false],
        '205' => ['Basic', 3000.00, 2, false],
        '206' => ['Basic', 3000.00, 2, false],
        '211' => ['Basic', 3000.00, 2, false],
        '213' => ['Basic', 2500.00, 1, false],
        '215' => ['Basic', 3200.00, 2, false],

        // Air-Conditioned Rooms (Sub-metered, is_metered = 1)
        '207' => ['Aircon', 3600.00, 2, true],
        '208' => ['Aircon', 3600.00, 2, true],
        '209' => ['Aircon', 3600.00, 2, true],
        '210' => ['Aircon', 3600.00, 2, true],
        '212' => ['Aircon', 3800.00, 2, true],
        '214' => ['Aircon', 3700.00, 2, true],
    ];

    public function run(): void
    {
        // 1. Resolve staff accounts without altering or recreating tbl_user
        $owner = User::where('role', 'Owner')->first() ?? User::first();
        if (! $owner) {
            $this->command?->error('No staff accounts found in tbl_user. Run UserSeeder first.');

            return;
        }

        $manager = User::where('role', 'Manager')->first() ?? $owner;
        $caretaker = User::where('role', 'Caretaker')->first() ?? $manager;

        DB::transaction(function () use ($owner, $manager, $caretaker) {
            // Clean slate for seeded domain records (strictly excluding tbl_user)
            Schema::disableForeignKeyConstraints();
            Visitor::query()->delete();
            Payment::query()->delete();
            Concern::query()->delete();
            Bill::query()->delete();
            MeterReading::query()->delete();
            Appliance::query()->delete();
            Occupant::query()->delete();
            Lease::query()->delete();
            Inquiry::query()->delete();
            Tenant::query()->delete();
            Room::query()->delete();
            Schema::enableForeignKeyConstraints();

            // 1. System Settings (tbl_setting)
            $this->seedSettings($owner);

            // 2. Physical Room Inventory (tbl_room)
            $rooms = $this->seedRooms();

            // 3. Prospective Inquiries (tbl_inquiry)
            $this->seedInquiries($rooms, $caretaker);

            // 4. Tenants, Leases, Occupants, Appliances, & Baseline Readings
            $leases = $this->seedLeasesAndTenants($rooms, $owner, $manager);

            // 5. Monthly Bills & Payment Ledger (tbl_bill, tbl_payment, tbl_meter_reading)
            $this->seedBillsAndLedger($leases, $owner, $manager);

            // 6. Tenant Concerns (tbl_concern)
            $this->seedConcerns($rooms, $leases, $caretaker, $manager);

            // 7. Visitor Logs (tbl_visitor)
            $this->seedVisitors($leases);
        });

        $this->command?->info('RealisticDataSeeder successfully completed: 15 rooms, 11 tenancies, inquiries, ledger records, and maintenance concerns seeded.');
    }

    /**
     * Seed baseline operational settings.
     */
    private function seedSettings(User $owner): void
    {
        Setting::updateOrCreate(
            ['setting_id' => 1],
            [
                'water_rate' => 200.00,
                'appliance_fee' => 200.00,
                'standard_basic_rent' => 3000.00,
                'standard_aircon_rent' => 3600.00,
                'default_room_capacity' => 2,
                'kwh_rate' => 12.00,
                'grace_days' => 3,
                'backup_time' => '02:00',
                'penalty_basis' => 'Per day of rent',
                'penalty_rate' => 1.00,
                'refund_days' => 7,
                'updated_by' => $owner->user_id,
            ]
        );
    }

    /**
     * Seed the 15-room property schedule with realistic initial status.
     *
     * @return array<string, Room>
     */
    private function seedRooms(): array
    {
        $rooms = [];
        $vacantRoomNumbers = ['203', '205', '210', '213'];

        foreach (self::ROOM_SCHEDULE as $roomNo => [$type, $rent, $max, $metered]) {
            $roomNoStr = (string) $roomNo;
            $isVacant = in_array($roomNoStr, $vacantRoomNumbers, true);
            $room = Room::create([
                'room_no' => $roomNoStr,
                'room_type' => $type,
                'is_metered' => $metered,
                'max_occupants' => $max,
                'monthly_rent' => $rent,
                'status' => $isVacant ? 'Vacant' : 'Occupied',
                // Room 206 is currently Occupied with expected vacation in 2 weeks
                'expected_vacant_date' => $roomNoStr === '206' ? today()->addWeeks(2)->toDateString() : null,
            ]);

            $rooms[$roomNoStr] = $room;
        }

        return $rooms;
    }

    /**
     * Seed 8 realistic prospect leads.
     */
    private function seedInquiries(array $rooms, User $caretaker): void
    {
        $inquiries = [
            // 4 Waiting prospects (strictly room_id = null)
            [
                'prospect_name' => 'Danilo Ramos',
                'contact_no' => '09171234567',
                'preferred_type' => 'Basic',
                'no_of_occupants' => 1,
                'channel' => 'Walk-in',
                'via_representative' => false,
                'wants_contact' => true,
                'inquiry_date' => today()->subDays(5)->toDateString(),
                'status' => 'Waiting',
                'room_id' => null,
                'remarks' => 'Looking for ground floor Basic room near front entrance.',
            ],
            [
                'prospect_name' => 'Jessa Mae Castro',
                'contact_no' => '09182345678',
                'preferred_type' => 'Aircon',
                'no_of_occupants' => 2,
                'channel' => 'Call',
                'via_representative' => false,
                'wants_contact' => true,
                'inquiry_date' => today()->subDays(3)->toDateString(),
                'status' => 'Waiting',
                'room_id' => null,
                'remarks' => 'Working professional couple in Bajada looking for sub-metered unit.',
            ],
            [
                'prospect_name' => 'Christian Paul Reyes',
                'contact_no' => '09203456789',
                'preferred_type' => 'Any',
                'no_of_occupants' => 1,
                'channel' => 'Call',
                'via_representative' => true,
                'wants_contact' => false,
                'inquiry_date' => today()->subDays(2)->toDateString(),
                'status' => 'Waiting',
                'room_id' => null,
                'remarks' => 'Inquired via sister; ready to move in next month.',
            ],
            [
                'prospect_name' => 'Mary Grace Villanueva',
                'contact_no' => '09224567890',
                'preferred_type' => 'Aircon',
                'no_of_occupants' => 2,
                'channel' => 'Walk-in',
                'via_representative' => false,
                'wants_contact' => true,
                'inquiry_date' => today()->subDays(1)->toDateString(),
                'status' => 'Waiting',
                'room_id' => null,
                'remarks' => 'Followed up on vacant aircon units for immediate occupancy.',
            ],

            // 2 Rented prospects (linked to converted rooms)
            [
                'prospect_name' => 'Maria Santos',
                'contact_no' => '09191122334',
                'preferred_type' => 'Basic',
                'no_of_occupants' => 1,
                'channel' => 'Walk-in',
                'via_representative' => false,
                'wants_contact' => false,
                'inquiry_date' => today()->subMonths(5)->toDateString(),
                'status' => 'Rented',
                'room_id' => $rooms['201']->room_id,
                'remarks' => 'Converted to lease for Room 201.',
            ],
            [
                'prospect_name' => 'Junrey Dalisay',
                'contact_no' => '09212233445',
                'preferred_type' => 'Aircon',
                'no_of_occupants' => 2,
                'channel' => 'Call',
                'via_representative' => false,
                'wants_contact' => false,
                'inquiry_date' => today()->subMonths(4)->toDateString(),
                'status' => 'Rented',
                'room_id' => $rooms['207']->room_id,
                'remarks' => 'Converted to lease for Room 207.',
            ],

            // 1 Referred prospect
            [
                'prospect_name' => 'Arnel Mendoza',
                'contact_no' => '09335678901',
                'preferred_type' => 'Aircon',
                'no_of_occupants' => 2,
                'channel' => 'Walk-in',
                'via_representative' => false,
                'wants_contact' => false,
                'inquiry_date' => today()->subDays(12)->toDateString(),
                'status' => 'Referred',
                'room_id' => null,
                'remarks' => 'Requested car parking space; referred to adjacent apartment compound',
            ],

            // 1 Declined prospect
            [
                'prospect_name' => 'Joshua Tan',
                'contact_no' => '09456789012',
                'preferred_type' => 'Basic',
                'no_of_occupants' => 1,
                'channel' => 'Walk-in',
                'via_representative' => false,
                'wants_contact' => false,
                'inquiry_date' => today()->subDays(8)->toDateString(),
                'status' => 'Declined',
                'room_id' => null,
                'remarks' => 'Enrolled full-time student; could not comply with house non-student policy',
            ],
        ];

        foreach ($inquiries as $data) {
            Inquiry::create($data + [
                'user_id' => $caretaker->user_id,
            ]);
        }
    }

    /**
     * Seed tenants, leases, occupants, appliances, and move-in baseline readings.
     *
     * @return array<string, Lease>
     */
    private function seedLeasesAndTenants(array $rooms, User $owner, User $manager): array
    {
        $tenancyDefinitions = [
            '201' => [
                'name' => 'Maria Santos', 'contact' => '09191122334', 'id_type' => 'UMID', 'id_no' => '1234-5678901-2',
                'province' => 'Purok 3, Tagum City, Davao del Norte', 'job' => 'Accountant',
                'months_ago' => 3, 'due_day' => 10, 'co_tenant' => null,
                'appliance' => 'Electric Rice Cooker', 'payment_mode' => 'Cash',
            ],
            '202' => [
                'name' => 'Bea Alonzo', 'contact' => '09226677889', 'id_type' => 'PhilSys National ID (PCN)', 'id_no' => '1234-5678-9012-3456',
                'province' => 'Zone 2, Panabo City, Davao del Norte', 'job' => 'Call Center Team Lead',
                'months_ago' => 4, 'due_day' => 15, 'co_tenant' => 'Dominic Roque',
                'appliance' => 'Electric Rice Cooker', 'payment_mode' => 'Cash',
            ],
            '204' => [
                'name' => 'Ryan Morales', 'contact' => '09237788990', 'id_type' => 'UMID', 'id_no' => 'CRN-87654321',
                'province' => 'Purok 5, Mati City, Davao Oriental', 'job' => 'Civil Engineer',
                'months_ago' => 3, 'due_day' => 20, 'co_tenant' => null,
                'appliance' => null, 'payment_mode' => 'GCash',
            ],
            '206' => [
                'name' => 'Carmela Bautista', 'contact' => '09248899001', 'id_type' => "Driver's License", 'id_no' => 'D02-21-987654',
                'province' => 'Brgy. Central, Polomolok, South Cotabato', 'job' => 'Marketing Specialist',
                'months_ago' => 5, 'due_day' => 25, 'co_tenant' => null,
                'appliance' => null, 'payment_mode' => 'Cash',
            ],
            '207' => [
                'name' => 'Junrey Dalisay', 'contact' => '09212233445', 'id_type' => "Driver's License", 'id_no' => 'N01-23-456789',
                'province' => 'Purok 7, General Santos City, South Cotabato', 'job' => 'Operations Manager',
                'months_ago' => 4, 'due_day' => 10, 'co_tenant' => 'Rowena Dalisay',
                'appliance' => null, 'payment_mode' => 'GCash',
            ],
            '208' => [
                'name' => 'Rodolfo Garcia', 'contact' => '09183344556', 'id_type' => 'PhilSys National ID (PCN)', 'id_no' => '9876-5432-1098-7654',
                'province' => 'Brgy. San Jose, Digos City, Davao del Sur', 'job' => 'Software Engineer',
                'months_ago' => 3, 'due_day' => 15, 'co_tenant' => null,
                'appliance' => null, 'payment_mode' => 'GCash',
            ],
            '209' => [
                'name' => 'Katherine Bernardo', 'contact' => '09259900112', 'id_type' => 'UMID', 'id_no' => '5678-1234567-9',
                'province' => 'Brgy. Libertad, Butuan City, Agusan del Norte', 'job' => 'Pharmacist',
                'months_ago' => 3, 'due_day' => 20, 'co_tenant' => 'Daniel Padilla',
                'appliance' => null, 'payment_mode' => 'Cash',
            ],
            '211' => [
                'name' => 'Eduardo Manalo', 'contact' => '09205566778', 'id_type' => 'Other', 'id_no' => '12-345678901-2',
                'province' => 'Poblacion, Nabunturan, Davao de Oro', 'job' => 'Store Supervisor',
                'months_ago' => 2, 'due_day' => 25, 'co_tenant' => 'Teresa Manalo',
                'appliance' => 'Electric Rice Cooker', 'payment_mode' => 'Cash',
            ],
            '212' => [
                'name' => 'Michelle De Guzman', 'contact' => '09260011223', 'id_type' => "Driver's License", 'id_no' => 'N03-24-112233',
                'province' => 'Purok 1, Malaybalay City, Bukidnon', 'job' => 'Financial Analyst',
                'months_ago' => 2, 'due_day' => 30, 'co_tenant' => null,
                'appliance' => null, 'payment_mode' => 'GCash',
            ],
            '214' => [
                'name' => 'Ronald Pascual', 'contact' => '09271122334', 'id_type' => 'PhilSys National ID (PCN)', 'id_no' => '5566-7788-9900-1122',
                'province' => 'Brgy. Mankilam, Tagum City, Davao del Norte', 'job' => 'Logistics Officer',
                'months_ago' => 4, 'due_day' => 30, 'co_tenant' => 'Luzviminda Pascual',
                'appliance' => null, 'payment_mode' => 'Cash',
            ],
            '215' => [
                'name' => 'Gerald Anderson', 'contact' => '09282233445', 'id_type' => 'UMID', 'id_no' => '3344-5566778-1',
                'province' => 'Zone 4, Koronadal City, South Cotabato', 'job' => 'HR Supervisor',
                'months_ago' => 3, 'due_day' => 15, 'co_tenant' => null,
                'appliance' => null, 'payment_mode' => 'Cash',
            ],
        ];

        $paymentService = app(PaymentService::class);
        $leases = [];
        $ackCounter = 1;

        foreach ($tenancyDefinitions as $roomNo => $def) {
            $room = $rooms[$roomNo];
            $rent = (float) $room->monthly_rent;

            // Compute exact deposit breakdown summing to monthly_rent
            $maintenance = 500.00;
            $damage = round($rent * 0.30, 2);
            $bills = round($rent * 0.30, 2);
            $keys = round($rent - $maintenance - $damage - $bills, 2);

            // Compute move-in date relative to today
            $moveInDate = today()->subMonthsNoOverflow($def['months_ago'])->startOfMonth()->day($def['due_day']);
            $moaDate = $moveInDate->copy()->subDay();

            // 1. Create Tenant
            $tenant = Tenant::create([
                'full_name' => $def['name'],
                'contact_no' => $def['contact'],
                'valid_id_type' => $def['id_type'],
                'valid_id_no' => $def['id_no'],
                'provincial_address' => $def['province'],
                'occupation' => $def['job'],
                'status' => 'Active',
                'created_at' => $moaDate,
            ]);

            // 2. Create Lease (4 months for Basic, 6 months for Aircon lock-in)
            $minStay = $room->room_type === 'Aircon' ? Lease::MIN_STAY_AIRCON : Lease::MIN_STAY_BASIC;
            $lease = Lease::create([
                'tenant_id' => $tenant->tenant_id,
                'room_id' => $room->room_id,
                'moa_date' => $moaDate->toDateString(),
                'move_in_date' => $moveInDate->toDateString(),
                'due_day' => $def['due_day'],
                'monthly_rent' => $rent,
                'min_stay_months' => $minStay,
                'advance_amount' => $rent,
                'deposit_maintenance' => $maintenance,
                'deposit_damage' => $damage,
                'deposit_bills' => $bills,
                'deposit_keys' => $keys,
                'status' => 'Active',
                'user_id' => $manager->user_id,
            ]);

            // 3. Create Occupants (Principal and optional Co-tenant)
            Occupant::create([
                'lease_id' => $lease->lease_id,
                'full_name' => $tenant->full_name,
                'is_principal' => true,
                'date_added' => $moveInDate->toDateString(),
            ]);

            if ($def['co_tenant']) {
                Occupant::create([
                    'lease_id' => $lease->lease_id,
                    'full_name' => $def['co_tenant'],
                    'is_principal' => false,
                    'date_added' => $moveInDate->toDateString(),
                ]);
            }

            // 4. Initial move-in baseline reading for metered aircon rooms
            if ($room->is_metered) {
                MeterReading::create([
                    'room_id' => $room->room_id,
                    'reading_date' => $moveInDate->toDateString(),
                    'previous_reading' => 1050.00,
                    'current_reading' => 1050.00,
                    'rate_per_kwh' => 12.00,
                    'source' => 'Meter',
                    'user_id' => $owner->user_id,
                ]);
            }

            // 5. Declared appliance for basic room
            if ($def['appliance'] && ! $room->is_metered) {
                Appliance::create([
                    'lease_id' => $lease->lease_id,
                    'appliance_name' => $def['appliance'],
                    'monthly_fee' => 200.00,
                    'date_added' => $moveInDate->toDateString(),
                    'source' => 'Declared',
                    'user_id' => $owner->user_id,
                ]);
            }

            // 6. Upfront initial move-in bill (Advance + Deposits = 2x rent)
            $upfrontTotal = round($rent * 2, 2);
            $initialBill = Bill::create([
                'lease_id' => $lease->lease_id,
                'reading_id' => null,
                'period_start' => $moveInDate->toDateString(),
                'due_date' => $moveInDate->toDateString(),
                'rent_amount' => $rent,
                'water_amount' => 0.00,
                'electricity_amount' => 0.00,
                'appliance_amount' => 0.00,
                'penalty_amount' => 0.00,
                'other_charges' => $rent, // Sum of deposits
                'total_amount' => $upfrontTotal,
                'status' => 'Paid',
                'is_initial' => true,
                'user_id' => $manager->user_id,
            ]);

            // Upfront payment record
            $isGcash = $def['payment_mode'] === 'GCash';
            Payment::create([
                'bill_id' => $initialBill->bill_id,
                'amount' => $upfrontTotal,
                'payment_date' => $moaDate->toDateString(),
                'mode' => $def['payment_mode'],
                'gcash_ref_no' => $isGcash ? '9000'.str_pad((string) $ackCounter, 9, '0', STR_PAD_LEFT) : null,
                'ack_no' => 'ACK-'.today()->year.'-'.str_pad((string) $ackCounter++, 4, '0', STR_PAD_LEFT),
                'user_id' => $isGcash ? $owner->user_id : $manager->user_id,
            ]);

            $leases[$roomNo] = $lease;
        }

        return $leases;
    }

    /**
     * Seed monthly bills, meter readings, payments, and targeted active/overdue scenarios.
     */
    private function seedBillsAndLedger(array $leases, User $owner, User $manager): void
    {
        $billingService = app(BillingService::class);
        $ackCounter = Payment::count() + 1;

        // Current meter tracker for aircon rooms
        $currentReadings = [
            '207' => 1050.00,
            '208' => 1050.00,
            '209' => 1050.00,
            '212' => 1050.00,
            '214' => 1050.00,
        ];

        foreach ($leases as $roomNo => $lease) {
            $lease->loadMissing(['room', 'occupants', 'appliances']);
            $dueDay = (int) $lease->due_day;

            // Generate consecutive monthly billing periods up to 7 days ahead of today
            while (($dueDate = $billingService->nextDueDate($lease))->lte(today()->addDays(BillingService::DAYS_AHEAD))) {
                // Meter reading calculation if metered
                $readingRecord = null;
                $kwhUsed = 0.00;
                if ($lease->room->is_metered) {
                    $prev = $currentReadings[(string) $roomNo];
                    $kwhUsed = round(70.00 + (($dueDate->month * 7 + (int) $roomNo) % 40), 2); // 70 - 110 kWh
                    $curr = round($prev + $kwhUsed, 2);
                    $currentReadings[(string) $roomNo] = $curr;

                    $readingRecord = MeterReading::create([
                        'room_id' => $lease->room->room_id,
                        'reading_date' => $dueDate->copy()->subDay()->toDateString(),
                        'previous_reading' => $prev,
                        'current_reading' => $curr,
                        'rate_per_kwh' => 12.00,
                        'source' => 'Meter',
                        'user_id' => $owner->user_id,
                    ]);
                }

                // Headcount and utility calculations
                $occupantCount = $lease->occupants()->count();
                $waterAmount = round(200.00 * $occupantCount, 2); // ₱200 for 1 occupant, ₱400 for 2 occupants
                $applianceAmount = $lease->room->is_metered ? 0.00 : round(200.00 * $lease->appliances()->count(), 2);
                $elecAmount = $lease->room->is_metered ? round($kwhUsed * 12.00, 2) : 0.00;
                $rentAmount = (float) $lease->monthly_rent;
                $totalAmount = round($rentAmount + $waterAmount + $applianceAmount + $elecAmount, 2);

                [$periodStart, $periodEnd] = $billingService->periodFor($lease->move_in_date, $dueDay, $dueDate);

                // TARGETED BILL SCENARIO 1: Bills Due Soon (Room 201 due Oct 10, Room 208 due Oct 15)
                if (in_array((string) $roomNo, ['201', '208'], true) && $dueDate->gte(today()) && $dueDate->lte(today()->addDays(7))) {
                    Bill::create([
                        'lease_id' => $lease->lease_id,
                        'reading_id' => $readingRecord?->reading_id,
                        'period_start' => $periodStart->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'rent_amount' => $rentAmount,
                        'water_amount' => $waterAmount,
                        'electricity_amount' => $elecAmount,
                        'appliance_amount' => $applianceAmount,
                        'penalty_amount' => 0.00,
                        'other_charges' => 0.00,
                        'total_amount' => $totalAmount,
                        'status' => 'Unpaid',
                        'is_initial' => false,
                        'user_id' => $manager->user_id,
                    ]);

                    continue;
                }

                // TARGETED BILL SCENARIO 2: Overdue Bill (Room 211 due on Sep 25 - past due + 3 days grace)
                if ((string) $roomNo === '211' && $dueDate->lt(today()->subDays(3))) {
                    $daysLate = (int) $dueDate->copy()->addDays(3)->diffInDays(today(), false);
                    $penaltyAmount = round($rentAmount * 0.01 * $daysLate, 2); // 1% per day of delayed rent
                    $overdueTotal = round($totalAmount + $penaltyAmount, 2);

                    Bill::create([
                        'lease_id' => $lease->lease_id,
                        'reading_id' => $readingRecord?->reading_id,
                        'period_start' => $periodStart->toDateString(),
                        'due_date' => $dueDate->toDateString(),
                        'rent_amount' => $rentAmount,
                        'water_amount' => $waterAmount,
                        'electricity_amount' => $elecAmount,
                        'appliance_amount' => $applianceAmount,
                        'penalty_amount' => $penaltyAmount,
                        'other_charges' => 0.00,
                        'total_amount' => $overdueTotal,
                        'status' => 'Overdue',
                        'is_initial' => false,
                        'user_id' => $manager->user_id,
                    ]);

                    continue;
                }

                // Normal past month bill: Generated and Paid
                $bill = Bill::create([
                    'lease_id' => $lease->lease_id,
                    'reading_id' => $readingRecord?->reading_id,
                    'period_start' => $periodStart->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'rent_amount' => $rentAmount,
                    'water_amount' => $waterAmount,
                    'electricity_amount' => $elecAmount,
                    'appliance_amount' => $applianceAmount,
                    'penalty_amount' => 0.00,
                    'other_charges' => 0.00,
                    'total_amount' => $totalAmount,
                    'status' => 'Paid',
                    'is_initial' => false,
                    'user_id' => $manager->user_id,
                ]);

                // Payment record
                $isGcash = ((int) $roomNo) % 2 === 0;
                $paymentDate = $dueDate->copy()->subDay()->min(today());
                Payment::create([
                    'bill_id' => $bill->bill_id,
                    'amount' => $totalAmount,
                    'payment_date' => $paymentDate->toDateString(),
                    'mode' => $isGcash ? 'GCash' : 'Cash',
                    'gcash_ref_no' => $isGcash ? '9000'.str_pad((string) $ackCounter, 9, '0', STR_PAD_LEFT) : null,
                    'ack_no' => 'ACK-'.today()->year.'-'.str_pad((string) $ackCounter++, 4, '0', STR_PAD_LEFT),
                    'user_id' => $isGcash ? $owner->user_id : $manager->user_id,
                ]);
            }
        }
    }

    /**
     * Seed 5 realistic maintenance tickets:
     * - Mix: Maintenance, Professional repair, Shared area
     * - Statuses: 2 Open, 1 In progress, 2 Resolved
     */
    private function seedConcerns(array $rooms, array $leases, User $caretaker, User $manager): void
    {
        $concerns = [
            // 1. Open, Urgent (Room 204 - Plumbing)
            [
                'room_id' => $rooms['204']->room_id,
                'lease_id' => $leases['204']->lease_id,
                'category' => 'Plumbing',
                'priority' => 'Urgent',
                'channel' => 'Verbal',
                'description' => 'Clogged toilet in Room 204 after heavy flushing',
                'reported_at' => today()->subHours(4),
                'reported_by' => $caretaker->user_id,
                'assigned_to' => $caretaker->user_id,
                'started_at' => null,
                'started_by' => null,
                'status' => 'Open',
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution' => null,
                'cost' => null,
                'charged_to_tenant' => false,
            ],

            // 2. Open, Routine (Shared Area - Lights)
            [
                'room_id' => null, // Shared Area
                'lease_id' => null,
                'category' => 'Lights',
                'priority' => 'Routine',
                'channel' => 'Text',
                'description' => 'Hallway fluorescent light flickering on 2nd floor landing',
                'reported_at' => today()->subDays(1)->setTime(18, 30),
                'reported_by' => $caretaker->user_id,
                'assigned_to' => $manager->user_id,
                'started_at' => null,
                'started_by' => null,
                'status' => 'Open',
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution' => null,
                'cost' => null,
                'charged_to_tenant' => false,
            ],

            // 3. In progress, Routine (Room 207 - Professional repair / Aircon)
            [
                'room_id' => $rooms['207']->room_id,
                'lease_id' => $leases['207']->lease_id,
                'category' => 'Aircon',
                'priority' => 'Routine',
                'channel' => 'Group chat',
                'description' => 'Aircon cooling efficiency low; outdoor condenser fan vibrating loudly',
                'reported_at' => today()->subDays(3)->setTime(10, 15),
                'reported_by' => $manager->user_id,
                'assigned_to' => $caretaker->user_id,
                'started_at' => today()->subDays(2)->setTime(9, 0),
                'started_by' => $caretaker->user_id,
                'status' => 'In progress',
                'resolved_at' => null,
                'resolved_by' => null,
                'resolution' => null,
                'cost' => null,
                'charged_to_tenant' => false,
            ],

            // 4. Resolved, Routine (Room 202 - Maintenance)
            [
                'room_id' => $rooms['202']->room_id,
                'lease_id' => $leases['202']->lease_id,
                'category' => 'Door/Lock/Keys',
                'priority' => 'Routine',
                'channel' => 'Verbal',
                'description' => 'Room entrance door knob loose and latch sticking',
                'reported_at' => today()->subDays(20)->setTime(8, 0),
                'reported_by' => $caretaker->user_id,
                'assigned_to' => $caretaker->user_id,
                'started_at' => today()->subDays(19)->setTime(8, 30),
                'started_by' => $caretaker->user_id,
                'status' => 'Resolved',
                'resolved_at' => today()->subDays(19)->setTime(11, 45),
                'resolved_by' => $caretaker->user_id,
                'resolution' => 'Replaced with heavy-duty stainless steel cylindrical lockset and tested key operation.',
                'cost' => 450.00,
                'charged_to_tenant' => false,
            ],

            // 5. Resolved, Routine (Shared Area - Professional repair / Plumbing)
            [
                'room_id' => null, // Shared Area
                'lease_id' => null,
                'category' => 'Plumbing',
                'priority' => 'Routine',
                'channel' => 'Verbal',
                'description' => 'Common ground floor utility sink drain leaking under the cabinet',
                'reported_at' => today()->subDays(15)->setTime(14, 0),
                'reported_by' => $caretaker->user_id,
                'assigned_to' => $manager->user_id,
                'started_at' => today()->subDays(14)->setTime(9, 0),
                'started_by' => $caretaker->user_id,
                'status' => 'Resolved',
                'resolved_at' => today()->subDays(14)->setTime(12, 30),
                'resolved_by' => $manager->user_id,
                'resolution' => 'Replaced worn P-trap gasket and re-sealed connection with plumbing thread tape.',
                'cost' => 350.00,
                'charged_to_tenant' => false,
            ],
        ];

        foreach ($concerns as $concernData) {
            Concern::create($concernData);
        }
    }

    /**
     * Seed 2 visitor entries honoring the contractual 2-day limit.
     */
    private function seedVisitors(array $leases): void
    {
        // Visitor 1: Parent (1 day stay)
        Visitor::create([
            'lease_id' => $leases['201']->lease_id,
            'visitor_name' => 'Lourdes Santos',
            'relationship' => 'Parent',
            'arrival_date' => today()->subDays(5)->toDateString(),
            'departure_date' => today()->subDays(4)->toDateString(),
        ]);

        // Visitor 2: Sibling (2 days stay)
        Visitor::create([
            'lease_id' => $leases['207']->lease_id,
            'visitor_name' => 'Mark Dalisay',
            'relationship' => 'Sibling',
            'arrival_date' => today()->subDays(3)->toDateString(),
            'departure_date' => today()->subDays(1)->toDateString(),
        ]);
    }
}
