<?php

namespace Tests\Feature;

use App\Models\Appliance;
use App\Models\Bill;
use App\Models\Concern;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Moveout;
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
use Illuminate\Support\Facades\Storage;
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

    public function test_seventeen_rooms_occupied_vacating_and_vacant(): void
    {
        $this->assertSame(array_map('strval', range(201, 217)), Room::orderBy('room_no')->pluck('room_no')->all());
        $this->assertSame(9, Room::where('status', 'Occupied')->count());
        // Vacating until their move-out is settled: notice only (206), awaiting approval (214), Refunded (216), Excess billed (217).
        $this->assertSame(['206', '214', '216', '217'], Room::where('status', 'Vacating')->orderBy('room_no')->pluck('room_no')->all());
        // 203, 205, 208 were never leased; 215 was freed by a settled move-out.
        $this->assertSame(['203', '205', '208', '215'], Room::where('status', 'Vacant')->orderBy('room_no')->pluck('room_no')->all());

        // Aircon rooms have their own meter, Basic rooms do not.
        $this->assertSame(0, Room::where('room_type', 'Aircon')->where('is_metered', false)->count());
        $this->assertSame(0, Room::where('room_type', 'Basic')->where('is_metered', true)->count());
        $this->assertGreaterThan(0, Room::where('room_type', 'Aircon')->count());
    }

    public function test_fourteen_sample_tenants_with_varied_due_days(): void
    {
        $this->assertSame(14, Tenant::where('full_name', 'like', 'Sample Tenant %')->count());
        $this->assertSame(13, Lease::where('status', 'Active')->count());
        $this->assertSame(1, Lease::where('status', 'Ended')->count());
        $this->assertGreaterThanOrEqual(8, Lease::distinct()->count('due_day'), 'Due days should differ');
        $this->assertSame(0, Tenant::whereNull('provincial_address')->orWhereNull('occupation')->count(), "Renter's Profile filled in");

        foreach (Lease::with(['room', 'moveout'])->get() as $lease) {
            $this->assertTrue($lease->move_in_date->lt(today()), 'Move-in dates are in the past');
            $this->assertSame($lease->move_in_date->day, $lease->due_day, 'Due day = move-in day');
            $expected = $lease->status === 'Ended' ? 'Vacant' : ($lease->moveout ? 'Vacating' : 'Occupied');
            $this->assertSame($expected, $lease->room->status, "Room {$lease->room->room_no}");
        }
    }

    public function test_one_move_out_in_each_status(): void
    {
        $moveouts = Moveout::with(['lease.room', 'finalBill', 'excessBill', 'items'])->get()->keyBy(fn ($m) => $m->lease->room->room_no);
        $this->assertSame(['206', '214', '215', '216', '217'], $moveouts->keys()->map(fn ($k) => (string) $k)->sort()->values()->all());
        $this->assertEqualsCanonicalizing(Moveout::STATUSES, $moveouts->pluck('status')->unique()->values()->all());

        // 206: notice only (lease "Moving out", not inspected yet).
        $this->assertSame('Pending', $moveouts['206']->status);
        $this->assertNull($moveouts['206']->actual_date);
        $this->assertNull($moveouts['206']->inspected_at);
        $this->assertSame('Moving out', $moveouts['206']->lease->displayStatus());

        // Every move-out with an actual date was inspected (needed to compute and approve).
        $this->assertTrue($moveouts->filter(fn ($m) => $m->actual_date)->every(fn ($m) => $m->inspected_at !== null));

        // 216: approved, refund not handed over yet.
        $this->assertSame('Refunded', $moveouts['216']->status);
        $this->assertGreaterThan(0, (float) $moveouts['216']->refund_amount);
        $this->assertSame('Moving out', $moveouts['216']->lease->displayStatus());

        // 217: damage above the deposit: no refund, an unpaid excess bill.
        $m = $moveouts['217'];
        $this->assertSame('Excess billed', $m->status);
        $this->assertSame('0.00', $m->refund_amount);
        $this->assertGreaterThan(0, (float) $m->excess_amount);
        $this->assertSame($m->excess_amount, $m->excessBill->total_amount);
        $this->assertNotSame('Paid', $m->excessBill->status);
        $refundable = (float) $m->lease->deposit_damage + (float) $m->lease->deposit_bills + (float) $m->lease->deposit_keys;
        $this->assertSame(round((float) $m->unpaid_balance + (float) $m->damage_total - $refundable, 2), (float) $m->excess_amount);

        // 214: moved out, final bill (with the final reading), 2 items, computed, waiting for the Owner.
        $m = $moveouts['214'];
        $this->assertSame('Pending', $m->status);
        $this->assertNotNull($m->finalBill?->reading_id);
        $this->assertSame('0.00', $m->finalBill->rent_amount);
        $this->assertCount(2, $m->items);
        $this->assertSame(round((float) $m->items->sum('cost'), 2), (float) $m->damage_total);
        $this->assertGreaterThan(0, (float) $m->refund_amount);

        // 215: settled with a refund; its final bill was settled from the deposit (no payment).
        $m = $moveouts['215'];
        $this->assertSame('Settled', $m->status);
        $this->assertGreaterThan(0, (float) $m->refund_amount);
        $this->assertSame('Paid', $m->finalBill->status);
        $this->assertSame(0, $m->finalBill->payments()->count());
        $this->assertSame('Ended', $m->lease->status);
        $this->assertSame('Moved out', $m->lease->displayStatus());

        // Refundable deposit − unpaid − damage = refund (no early departures among the moved-out ones).
        foreach (['214', '215', '216'] as $room) {
            $m = $moveouts[$room];
            $lease = $m->lease;
            $expected = round((float) $lease->deposit_damage + (float) $lease->deposit_bills + (float) $lease->deposit_keys
                - (float) $m->unpaid_balance - (float) $m->damage_total, 2);
            $this->assertSame($expected, (float) $m->refund_amount, "Room {$room}");
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

        foreach (Lease::with(['room', 'moveout'])->get() as $lease) {
            // The excess bill of a settlement is due on the refund due date (decision 16), so it is left out here.
            $bills = $lease->bills()->when($lease->moveout?->excess_bill_id, fn ($q, $id) => $q->where('bill_id', '!=', $id))
                ->orderBy('due_date')->get();
            $firstDue = $billing->firstDueDate($lease->move_in_date, $lease->due_day);

            // A recent move-in whose first due date has not come yet (depends on today's date) has no bills.
            if ($firstDue->gt($lease->moveout?->actual_date ?? today())) {
                $this->assertEmpty($bills, "Lease {$lease->lease_id} has a bill before its first due date");
                $this->assertSame($firstDue->toDateString(), $billing->nextDueDate($lease)->toDateString());

                continue;
            }
            $this->assertNotEmpty($bills, "Lease {$lease->lease_id} should have past bills");

            // Oldest bill = first due date; the next one to generate is in the future (or after the move-out date).
            $this->assertSame($firstDue->toDateString(), $bills->first()->due_date->toDateString());
            $this->assertTrue($billing->nextDueDate($lease)->gt($lease->moveout?->actual_date ?? today()),
                "Room {$lease->room->room_no}: next due {$billing->nextDueDate($lease)->toDateString()}");
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
        $this->assertGreaterThan(0, Concern::whereNull('room_id')->where('category', 'Plumbing')->count(), 'A shared-area plumbing concern');
        $this->assertTrue(Concern::where('status', 'Resolved')->get()->every(fn ($c) => $c->resolved_at && $c->resolution));

        // Every category is used once at least.
        $this->assertEqualsCanonicalizing(array_keys(Concern::CATEGORIES), Concern::distinct()->pluck('category')->all());

        // Timeline: who reported every concern, who started the in-progress ones, who resolved the resolved ones, in order.
        foreach (Concern::all() as $c) {
            $this->assertNotNull($c->reported_by);
            if ($c->status === 'In progress') {
                $this->assertNotNull($c->started_by);
                $this->assertTrue($c->started_at->gte($c->reported_at));
            }
            if ($c->status === 'Resolved') {
                $this->assertNotNull($c->resolved_by);
                $this->assertTrue($c->resolved_at->gte($c->started_at ?? $c->reported_at));
            }
        }

        // Sample photos, stored privately: a "problem" photo and, for the door knob, a "fixed" photo too.
        $this->assertSame(2, Concern::whereNotNull('photo_path')->count());
        $both = Concern::whereNotNull('photo_path')->whereNotNull('resolution_photo_path')->sole();
        Storage::disk('local')->assertExists([$both->photo_path, $both->resolution_photo_path]);

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
            Visitor::count(), Bill::count(), MeterReading::count(), Payment::count(), Concern::count(), Moveout::count()];
        $before = $counts();

        $this->seed(DemoSeeder::class);

        $this->assertSame($before, $counts());
        $this->assertSame(9, Room::where('status', 'Occupied')->count(), 'Room statuses are not reset');
    }
}
