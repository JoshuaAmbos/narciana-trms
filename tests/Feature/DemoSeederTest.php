<?php

namespace Tests\Feature;

use App\Models\Appliance;
use App\Models\Bill;
use App\Models\Concern;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Services\BillingService;
use App\Services\ConcernService;
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
            $this->assertTrue($bills->every(fn ($b) => $b->due_date->lte(today())));

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

    public function test_payments_cash_gcash_late_and_overdue(): void
    {
        $payments = Payment::with('bill', 'user')->get();

        // Cash received by the Manager, GCash by the Owner.
        $this->assertGreaterThan(0, $payments->where('mode', 'Cash')->count());
        $this->assertGreaterThan(0, $payments->where('mode', 'GCash')->count());
        $this->assertTrue($payments->where('mode', 'Cash')->every(fn ($p) => $p->user->role === 'Manager' && $p->gcash_ref_no === null));
        $this->assertTrue($payments->where('mode', 'GCash')->every(fn ($p) => $p->user->role === 'Owner' && preg_match('/^\d{13}$/', $p->gcash_ref_no)));

        // Every payment matches its bill, the bill is Paid, some carry a penalty.
        $this->assertTrue($payments->every(fn ($p) => $p->bill->status === 'Paid' && $p->amount === $p->bill->total_amount));
        $this->assertGreaterThan(0, Bill::where('penalty_amount', '>', 0)->count());
        $this->assertSame(0, Bill::where('status', '!=', 'Paid')->where('penalty_amount', '>', 0)->count(), 'Penalty is saved only when paid');

        // A couple of Overdue bills, and a few recent Unpaid ones.
        $this->assertGreaterThanOrEqual(2, Bill::where('status', 'Overdue')->count());
        $this->assertSame(0, Bill::where('status', 'Unpaid')->whereDate('due_date', '<', today()->subDays(3))->count(), 'Statuses refreshed');

        // Oldest bill first: no Paid bill after an unpaid one in the same lease.
        foreach (Lease::all() as $lease) {
            $statuses = $lease->bills()->orderBy('due_date')->pluck('status')->all();
            $firstUnpaid = collect($statuses)->search(fn ($s) => $s !== 'Paid');
            if ($firstUnpaid !== false) {
                $this->assertNotContains('Paid', array_slice($statuses, $firstUnpaid), "Lease {$lease->lease_id}");
            }
        }

        // ACK numbers: unique, per-year sequence without gaps.
        $acks = $payments->pluck('ack_no')->sort()->values();
        $this->assertSame($acks->count(), $acks->unique()->count());
        $this->assertSame('ACK-'.$payments->first()->payment_date->year.'-'.str_pad((string) $acks->count(), 4, '0', STR_PAD_LEFT), $acks->last());
    }

    public function test_concerns_open_in_progress_resolved_and_charged(): void
    {
        $this->assertGreaterThan(0, Concern::where('status', 'Open')->where('priority', 'Urgent')->count());
        $this->assertGreaterThan(0, Concern::where('status', 'In progress')->count());
        $this->assertGreaterThan(0, Concern::where('status', 'Resolved')->where('charged_to_tenant', false)->count());
        $this->assertGreaterThan(0, Concern::whereNull('room_id')->where('category', 'Shared area')->count());
        $this->assertTrue(Concern::where('status', 'Resolved')->get()->every(fn ($c) => $c->resolved_at && $c->resolution));

        // One charge already on a bill (and counted in its other charges), one waiting to be picked.
        $billed = Concern::whereNotNull('bill_id')->with('bill')->get();
        $this->assertCount(1, $billed);
        $this->assertSame($billed->first()->cost, $billed->first()->bill->other_charges);
        $waiting = Concern::where('charged_to_tenant', true)->whereNull('bill_id')->with('lease')->get();
        $this->assertCount(1, $waiting);
        $this->assertCount(1, app(ConcernService::class)->chargeable($waiting->first()->lease));

        // A repeated issue in one room.
        $this->assertTrue(Concern::whereNotNull('room_id')->get()->groupBy(fn ($c) => $c->room_id.$c->category)->contains(fn ($g) => $g->count() > 1));
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $counts = fn () => [Room::count(), Lease::count(), Tenant::count(), Inquiry::count(), Appliance::count(),
            Visitor::count(), Bill::count(), MeterReading::count(), Payment::count(), Concern::count()];
        $before = $counts();

        $this->seed(DemoSeeder::class);

        $this->assertSame($before, $counts());
        $this->assertSame(10, Room::where('status', 'Occupied')->count(), 'Occupied rooms are not reset');
    }

}
