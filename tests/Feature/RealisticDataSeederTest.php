<?php

namespace Tests\Feature;

use App\Models\Appliance;
use App\Models\Bill;
use App\Models\Concern;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visitor;
use App\Services\DashboardService;
use Database\Seeders\RealisticDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealisticDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed staff accounts & baseline settings
        $this->seed();
    }

    public function test_seeder_executes_cleanly_and_preserves_users(): void
    {
        $initialUsers = User::orderBy('user_id')->pluck('username', 'user_id')->all();

        $this->seed(RealisticDataSeeder::class);

        // Staff accounts must not be re-created or modified
        $this->assertSame($initialUsers, User::orderBy('user_id')->pluck('username', 'user_id')->all());
    }

    public function test_fifteen_room_inventory_and_occupancy_distribution(): void
    {
        $this->seed(RealisticDataSeeder::class);

        $this->assertSame(15, Room::count());

        // Basic rooms check
        $this->assertSame(['201', '202', '203', '204', '205', '206', '211', '213', '215'], Room::where('room_type', 'Basic')->orderBy('room_no')->pluck('room_no')->all());
        $this->assertSame(0, Room::where('room_type', 'Basic')->where('is_metered', true)->count());

        // Aircon rooms check
        $this->assertSame(['207', '208', '209', '210', '212', '214'], Room::where('room_type', 'Aircon')->orderBy('room_no')->pluck('room_no')->all());
        $this->assertSame(6, Room::where('room_type', 'Aircon')->where('is_metered', true)->count());

        // Rates & capacities
        $this->assertSame('3500.00', Room::where('room_no', '204')->value('monthly_rent'));
        $this->assertSame('2500.00', Room::where('room_no', '213')->value('monthly_rent'));
        $this->assertSame(1, Room::where('room_no', '213')->value('max_occupants'));
        $this->assertSame('3800.00', Room::where('room_no', '212')->value('monthly_rent'));
        $this->assertSame('3700.00', Room::where('room_no', '214')->value('monthly_rent'));

        // Occupancy distribution
        $this->assertSame(4, Room::where('status', 'Vacant')->count());
        $this->assertSame(['203', '205', '210', '213'], Room::where('status', 'Vacant')->orderBy('room_no')->pluck('room_no')->all());

        $this->assertSame(11, Room::where('status', 'Occupied')->count());

        // Vacating room 206
        $room206 = Room::where('room_no', '206')->firstOrFail();
        $this->assertSame('Occupied', $room206->status);
        $this->assertNotNull($room206->expected_vacant_date);
        $this->assertSame(today()->addWeeks(2)->toDateString(), $room206->expected_vacant_date->toDateString());
    }

    public function test_system_settings_baseline(): void
    {
        $this->seed(RealisticDataSeeder::class);

        $setting = Setting::firstOrFail();
        $this->assertSame('200.00', $setting->water_rate);
        $this->assertSame('200.00', $setting->appliance_fee);
        $this->assertSame('12.00', $setting->kwh_rate);
        $this->assertSame(3, $setting->grace_days);
        $this->assertSame('Per day of rent', $setting->penalty_basis);
        $this->assertSame('1.00', $setting->penalty_rate);
        $this->assertSame(7, $setting->refund_days);
    }

    public function test_prospective_inquiries(): void
    {
        $this->seed(RealisticDataSeeder::class);

        $this->assertSame(8, Inquiry::count());

        // 4 Waiting prospects with null room_id
        $waiting = Inquiry::where('status', 'Waiting')->get();
        $this->assertCount(4, $waiting);
        foreach ($waiting as $inq) {
            $this->assertNull($inq->room_id);
            $this->assertLessThanOrEqual(2, $inq->no_of_occupants);
            $this->assertMatchesRegularExpression('/^09\d{9}$/', $inq->contact_no);
        }

        // 2 Rented prospects linked to rooms
        $rented = Inquiry::where('status', 'Rented')->get();
        $this->assertCount(2, $rented);
        foreach ($rented as $inq) {
            $this->assertNotNull($inq->room_id);
        }

        // 1 Referred prospect
        $referred = Inquiry::where('status', 'Referred')->firstOrFail();
        $this->assertSame('Requested car parking space; referred to adjacent apartment compound', $referred->remarks);

        // 1 Declined prospect
        $declined = Inquiry::where('status', 'Declined')->firstOrFail();
        $this->assertSame('Enrolled full-time student; could not comply with house non-student policy', $declined->remarks);
    }

    public function test_tenancies_lock_in_and_financial_math(): void
    {
        $this->seed(RealisticDataSeeder::class);

        $this->assertSame(11, Lease::where('status', 'Active')->count());
        $this->assertSame(11, Tenant::where('status', 'Active')->count());

        foreach (Lease::with(['room', 'tenant'])->get() as $lease) {
            // Lock-in
            $expectedMinStay = $lease->room->room_type === 'Aircon' ? 6 : 4;
            $this->assertSame($expectedMinStay, (int) $lease->min_stay_months);

            // Due days strictly in [10, 15, 20, 25, 30]
            $this->assertContains((int) $lease->due_day, [10, 15, 20, 25, 30]);

            // Upfront advance amount = base monthly rent
            $this->assertEquals((float) $lease->monthly_rent, (float) $lease->advance_amount);

            // Deposit breakdown equals monthly rent
            $depositSum = (float) $lease->deposit_maintenance
                + (float) $lease->deposit_damage
                + (float) $lease->deposit_bills
                + (float) $lease->deposit_keys;
            $this->assertEquals((float) $lease->monthly_rent, round($depositSum, 2));
            $this->assertEquals(500.00, (float) $lease->deposit_maintenance);
        }
    }

    public function test_appliances_and_sub_meter_readings(): void
    {
        $this->seed(RealisticDataSeeder::class);

        // At least 3 declared appliances on basic room leases
        $appliances = Appliance::where('source', 'Declared')->get();
        $this->assertGreaterThanOrEqual(3, $appliances->count());
        foreach ($appliances as $appliance) {
            $this->assertSame('Electric Rice Cooker', $appliance->appliance_name);
            $this->assertSame('200.00', $appliance->monthly_fee);
        }

        // Metered rooms have baseline move-in reading of 1050.00
        $airconRooms = Room::where('room_type', 'Aircon')->get();
        foreach ($airconRooms as $room) {
            if ($room->status === 'Occupied') {
                $firstReading = MeterReading::where('room_id', $room->room_id)->orderBy('reading_date')->first();
                $this->assertNotNull($firstReading);
                $this->assertSame('1050.00', $firstReading->previous_reading);
            }
        }
    }

    public function test_targeted_billing_scenarios_and_dashboard_alerts(): void
    {
        $this->seed(RealisticDataSeeder::class);

        // 2 bills due soon within next 7 days, Unpaid
        $dueSoon = Bill::where('status', 'Unpaid')->get();
        $this->assertCount(2, $dueSoon);
        foreach ($dueSoon as $bill) {
            $this->assertTrue($bill->due_date->gte(today()));
            $this->assertTrue($bill->due_date->lte(today()->addDays(7)));
        }

        // 1 overdue bill past due + 3 grace days
        $overdue = Bill::where('status', 'Overdue')->get();
        $this->assertCount(1, $overdue);
        $overdueBill = $overdue->first();
        $this->assertTrue($overdueBill->due_date->lt(today()->subDays(3)));
        $this->assertGreaterThan(0, (float) $overdueBill->penalty_amount);

        // Headcount scaling for water: 1 occupant = 200, 2 occupants = 400
        $singleOccupantLease = Lease::whereHas('occupants', null, '=', 1)->firstOrFail();
        $doubleOccupantLease = Lease::whereHas('occupants', null, '=', 2)->firstOrFail();

        $singleBill = Bill::where('lease_id', $singleOccupantLease->lease_id)->where('is_initial', false)->firstOrFail();
        $doubleBill = Bill::where('lease_id', $doubleOccupantLease->lease_id)->where('is_initial', false)->firstOrFail();

        $this->assertSame('200.00', $singleBill->water_amount);
        $this->assertSame('400.00', $doubleBill->water_amount);

        // Staff Dashboard counts
        $dashboard = app(DashboardService::class)->forStaff();
        $this->assertSame(2, $dashboard['counts']['due_soon']);
        $this->assertSame(1, $dashboard['counts']['overdue_tenants']);
        $this->assertSame(4, $dashboard['counts']['vacant_rooms']);
        $this->assertSame(11, $dashboard['counts']['occupied_rooms']);
    }

    public function test_tenant_concerns_and_visitor_logs(): void
    {
        $this->seed(RealisticDataSeeder::class);

        // 5 concerns: 2 Open, 1 In progress, 2 Resolved
        $this->assertSame(5, Concern::count());
        $this->assertSame(2, Concern::where('status', 'Open')->count());
        $this->assertSame(1, Concern::where('status', 'In progress')->count());
        $this->assertSame(2, Concern::where('status', 'Resolved')->count());

        // Shared area concerns have null room_id
        $sharedConcerns = Concern::whereNull('room_id')->get();
        $this->assertGreaterThanOrEqual(1, $sharedConcerns->count());

        // Resolved concerns have filled resolution notes and costs
        foreach (Concern::where('status', 'Resolved')->get() as $c) {
            $this->assertNotNull($c->resolution);
            $this->assertNotNull($c->cost);
        }

        // 2 visitors with 1-2 day stays
        $this->assertSame(2, Visitor::count());
        foreach (Visitor::all() as $v) {
            $this->assertContains($v->relationship, ['Parent', 'Sibling']);
            $days = $v->arrival_date->diffInDays($v->departure_date);
            $this->assertGreaterThanOrEqual(1, $days);
            $this->assertLessThanOrEqual(2, $days);
        }
    }
}
