<?php

namespace Tests\Feature;

use App\Models\Appliance;
use App\Models\Bill;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Services\BillingService;
use App\Services\RoomAvailabilityService;
use App\Services\VisitorService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The demo data must match its description and be the same every time. */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();                   // Owner/Manager/Caretaker + settings (like migrate:fresh --seed)
        $this->seed(DemoSeeder::class);
    }

    public function test_thirteen_rooms_ten_occupied_three_vacant(): void
    {
        $this->assertSame(array_map('strval', range(201, 213)), Room::orderBy('room_no')->pluck('room_no')->all());
        $this->assertSame(10, Room::where('status', 'Occupied')->count());
        $this->assertSame(['203', '205', '208'], Room::where('status', 'Vacant')->orderBy('room_no')->pluck('room_no')->all());

        // Aircon rooms have their own meter, Basic rooms do not.
        $this->assertSame(0, Room::where('room_type', 'Aircon')->where('is_metered', false)->count());
        $this->assertSame(0, Room::where('room_type', 'Basic')->where('is_metered', true)->count());
        $this->assertGreaterThan(0, Room::where('room_type', 'Aircon')->count());
    }

    public function test_ten_sample_tenants_with_active_leases_and_varied_due_days(): void
    {
        $this->assertSame(10, Tenant::where('full_name', 'like', 'Sample Tenant %')->count());
        $this->assertSame(10, Lease::where('status', 'Active')->count());
        $this->assertGreaterThanOrEqual(8, Lease::distinct()->count('due_day'), 'Due days should differ');

        foreach (Lease::with('room')->get() as $lease) {
            $this->assertTrue($lease->move_in_date->lt(today()), 'Move-in dates are in the past');
            $this->assertSame($lease->move_in_date->day, $lease->due_day, 'Due day = move-in day');
            $this->assertSame('Occupied', $lease->room->status);
        }
    }

    public function test_deposits_total_one_month_rent(): void
    {
        foreach (Lease::all() as $lease) {
            $this->assertSame(round((float) $lease->monthly_rent, 2), $lease->depositTotal(), "Lease {$lease->lease_id}");
            $this->assertSame($lease->monthly_rent, $lease->advance_amount);
        }
    }

    public function test_co_tenants_appliances_and_visitors(): void
    {
        $this->assertGreaterThanOrEqual(2, Lease::has('occupants', '=', 2)->count(), 'Some leases have a co-tenant');

        // Appliances only on rooms without a meter, 1 to 2 each.
        $appliances = Appliance::with('lease.room')->get();
        $this->assertGreaterThan(0, $appliances->count());
        $this->assertTrue($appliances->every(fn ($a) => ! $a->lease->room->is_metered));
        $this->assertTrue($appliances->groupBy('lease_id')->every(fn ($g) => $g->count() >= 1 && $g->count() <= 2));

        // Two visitors, exactly one over 2 days.
        $rule = new VisitorService;
        $visitors = Visitor::all();
        $this->assertCount(2, $visitors);
        $this->assertSame(1, $visitors->filter(fn ($v) => $rule->isOverStay($v))->count());
    }

    public function test_every_vacant_room_has_a_matching_waiting_inquiry(): void
    {
        $service = app(RoomAvailabilityService::class);
        $matches = $service->matchesForAllRooms(Room::all());

        foreach (Room::where('status', 'Vacant')->get() as $room) {
            $this->assertNotEmpty($matches[$room->room_id], "Room {$room->room_no} should have a waiting prospect");
        }
        $this->assertCount(0, $service->rentalHolds(), 'No room is held by a pending Rented inquiry');
        $this->assertSame(3, Inquiry::where('status', 'Waiting')->count());
    }

    public function test_every_bill_already_due_exists_and_nothing_later(): void
    {
        $billing = app(BillingService::class);

        foreach (Lease::with('room')->get() as $lease) {
            $bills = $lease->bills()->orderBy('due_date')->get();
            $this->assertNotEmpty($bills, "Lease {$lease->lease_id} should have past bills");

            // Oldest bill = first due date; the next one to generate is in the future.
            $this->assertSame($billing->firstDueDate($lease->move_in_date, $lease->due_day)->toDateString(), $bills->first()->due_date->toDateString());
            $this->assertTrue($billing->nextDueDate($lease)->gt(today()));
            $this->assertTrue($bills->every(fn ($b) => $b->due_date->lte(today()) && $b->status === 'Unpaid'));

            foreach ($bills as $bill) {
                $sum = (float) $bill->rent_amount + (float) $bill->water_amount + (float) $bill->electricity_amount
                    + (float) $bill->appliance_amount + (float) $bill->penalty_amount + (float) $bill->other_charges;
                $this->assertSame(round($sum, 2), round((float) $bill->total_amount, 2));

                if ($lease->room->is_metered) {
                    $this->assertNotNull($bill->reading_id);
                    $this->assertGreaterThan(0, (float) $bill->electricity_amount);
                    $this->assertSame(0.0, (float) $bill->appliance_amount);
                } else {
                    $this->assertNull($bill->reading_id);
                    $this->assertSame(0.0, (float) $bill->electricity_amount);
                }
            }
        }

        // Each metered room: 1 move-in reading + 1 reading per bill.
        $metered = Lease::whereHas('room', fn ($q) => $q->where('is_metered', true))->get();
        $this->assertSame($metered->count() + Bill::whereNotNull('reading_id')->count(), MeterReading::count());
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $before = [Room::count(), Lease::count(), Tenant::count(), Inquiry::count(), Appliance::count(), Visitor::count(), Bill::count(), MeterReading::count()];

        $this->seed(DemoSeeder::class);

        $this->assertSame($before, [Room::count(), Lease::count(), Tenant::count(), Inquiry::count(), Appliance::count(), Visitor::count(), Bill::count(), MeterReading::count()]);
        $this->assertSame(10, Room::where('status', 'Occupied')->count(), 'Occupied rooms are not reset');
    }

}
