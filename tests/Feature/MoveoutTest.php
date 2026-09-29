<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Lease;
use App\Models\Moveout;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use App\Services\PenaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/**
 * Lease: Basic room 204, rent 3,500.00, 1 occupant, no appliances, move-in 25 Jun 2026, due day 25, min stay 4 months
 * (may leave from 25 Oct 2026). Deposits 1,000 maintenance + 1,500 damage + 700 bills + 300 keys: refundable 2,500.00.
 * Every regular bill = 3,500.00 rent + 200.00 water = 3,700.00.
 */
class MoveoutTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private User $manager;

    private Room $room;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
        $this->manager = User::factory()->role('Manager')->create();
        $this->travelTo(Carbon::parse('2026-07-01'));
        $this->room = $this->makeRoom('204');
        $this->lease = $this->makeLease($this->room, $this->manager);
    }

    /** Generate (and optionally pay on time) every regular bill due up to $until. */
    private function billsUntil(string $until, bool $payLast = true): void
    {
        $billing = app(BillingService::class);
        while (($due = $billing->nextDueDate($this->lease))->lte(Carbon::parse($until))) {
            $this->travelTo($due->copy()->subDays(2));
            $bill = $billing->generate($this->lease->refresh(), null, $due->toDateString(), $this->manager);
            if ($payLast || $due->lt(Carbon::parse($until)->startOfMonth())) {
                app(PaymentService::class)->record($bill, ['payment_date' => today()->toDateString(), 'mode' => 'Cash',
                    'amount' => app(PenaltyService::class)->forBill($bill, today())['total']], $this->manager);
            }
        }
    }

    private function notice(string $noticeDate, string $planned): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->manager)->post(route('moveouts.store'), [
            'lease_id' => $this->lease->lease_id, 'notice_date' => $noticeDate, 'planned_date' => $planned,
        ]);
    }

    private function moveout(): Moveout
    {
        return Moveout::firstOrFail();
    }

    private function recordActual(string $noticeDate, string $planned, string $actual): void
    {
        $this->actingAs($this->manager)->put(route('moveouts.update', $this->moveout()), [
            'notice_date' => $noticeDate, 'planned_date' => $planned, 'actual_date' => $actual,
        ])->assertSessionHasNoErrors();
    }

    private function finalBill(): void
    {
        $this->actingAs($this->manager)->post(route('moveouts.final-bill.store', $this->moveout()), [])->assertSessionHasNoErrors();
    }

    private function item(string $description, string $cost): void
    {
        $this->actingAs($this->manager)->post(route('moveouts.items.store', $this->moveout()), ['description' => $description, 'cost' => $cost])
            ->assertSessionHasNoErrors();
    }

    // ---------- Notice ----------

    public function test_notice_sets_the_room_vacating_and_warns_when_short(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01'));

        $this->notice('2026-09-01', '2026-09-20')
            ->assertSessionHas('warning', "Note: the MOA asks for one month's notice; this notice is only 19 days.");

        $this->room->refresh();
        $this->assertSame('Vacating', $this->room->status);
        $this->assertSame('2026-09-20', $this->room->expected_vacant_date->toDateString());
        $this->assertTrue($this->moveout()->early_departure, 'Before 25 Oct: early departure');
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Move-out notice', 'record_id' => $this->moveout()->moveout_id]);
    }

    public function test_one_month_notice_has_no_warning(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25'));
        $this->notice('2026-09-25', '2026-10-28')->assertSessionMissing('warning');
        $this->assertFalse($this->moveout()->early_departure);
    }

    public function test_notice_can_be_cancelled_before_moving_out(): void
    {
        $this->travelTo(Carbon::parse('2026-09-01'));
        $this->notice('2026-09-01', '2026-10-01');

        $this->actingAs($this->manager)->delete(route('moveouts.destroy', $this->moveout()))->assertRedirect(route('leases.show', $this->lease));

        $this->assertSame(0, Moveout::count());
        $this->assertSame('Occupied', $this->room->refresh()->status);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Move-out notice cancelled']);
    }

    public function test_an_moa_with_a_notice_cannot_be_cancelled(): void
    {
        $this->notice('2026-07-01', '2026-08-01');
        $this->actingAs($this->manager)->patch(route('leases.cancel', $this->lease))->assertForbidden();
    }

    // ---------- Refund flow ----------

    public function test_full_refund_flow(): void
    {
        $this->billsUntil('2026-10-25');                  // Jul–Oct bills generated and paid
        $this->travelTo(Carbon::parse('2026-10-28'));
        $this->notice('2026-09-25', '2026-10-28');
        $this->recordActual('2026-09-25', '2026-10-28', '2026-10-28');

        // Final bill: 25 Oct – 28 Oct, rent 0, water 200 (1 occupant).
        $this->finalBill();
        $final = $this->moveout()->finalBill;
        $this->assertSame('0.00', $final->rent_amount);
        $this->assertSame('200.00', $final->water_amount);
        $this->assertSame('2026-10-25', $final->period_start->toDateString());
        $this->assertSame('2026-10-28', $final->due_date->toDateString());

        $this->item('Wall paint scratches near bed', '300.00');

        // 2,500.00 − 200.00 (final bill) − 300.00 = 2,000.00 refund.
        $this->actingAs($this->manager)->post(route('moveouts.compute', $this->moveout()))->assertSessionHasNoErrors();
        $m = $this->moveout();
        $this->assertSame('200.00', $m->unpaid_balance);
        $this->assertSame('300.00', $m->damage_total);
        $this->assertSame('2000.00', $m->refund_amount);
        $this->assertSame('0.00', $m->excess_amount);
        $this->assertSame('2026-11-04', $m->refund_due_date->toDateString());

        $this->actingAs($this->manager)->patch(route('moveouts.approve', $m))->assertForbidden();
        $this->actingAs($this->owner)->patch(route('moveouts.approve', $m))->assertSessionHasNoErrors();

        $m->refresh();
        $this->assertSame('Refunded', $m->status);
        $this->assertSame('Paid', $final->refresh()->status, 'Final bill settled from the deposit');
        $this->assertSame(0, Payment::where('bill_id', $final->bill_id)->count(), 'No payment/ACK for a deposit settlement');
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Bill settled from deposit', 'record_id' => $final->bill_id]);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Settlement approved', 'record_id' => $m->moveout_id, 'user_id' => $this->owner->user_id]);
        $this->assertSame('Active', $this->lease->refresh()->status, 'Still active until the refund is handed over');

        $this->actingAs($this->manager)->patch(route('moveouts.release', $m))->assertForbidden();
        $this->actingAs($this->owner)->patch(route('moveouts.release', $m))->assertSessionHasNoErrors();

        $this->assertSame('Settled', $m->refresh()->status);

        // The statement still shows the approved figures (the final bill is now Paid, but it was deducted).
        $this->actingAs($this->owner)->get(route('moveouts.show', $m))
            ->assertSeeInOrder(['Less: unpaid bills', 'Bill due 28 Oct 2026', '(₱200.00)', 'Refund to tenant', '₱2,000.00'])
            ->assertDontSee('₱2,200.00');
        $this->assertSame('Ended', $this->lease->refresh()->status);
        $this->room->refresh();
        $this->assertSame('Vacant', $this->room->status);
        $this->assertNull($this->room->expected_vacant_date);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Refund released', 'record_id' => $m->moveout_id]);
    }

    public function test_settlement_statement_is_itemized(): void
    {
        $this->billsUntil('2026-10-25');
        $this->travelTo(Carbon::parse('2026-10-28'));
        $this->notice('2026-09-25', '2026-10-28');
        $this->recordActual('2026-09-25', '2026-10-28', '2026-10-28');
        $this->finalBill();
        $this->item('Adhesive hook marks', '300.00');
        $this->actingAs($this->manager)->post(route('moveouts.compute', $this->moveout()));

        $this->actingAs($this->owner)->get(route('moveouts.show', $this->moveout()))
            ->assertOk()
            ->assertSeeInOrder(['Total deposit', '₱3,500.00', 'Less: maintenance deposit', '₱1,000.00', 'Refundable deposit', '₱2,500.00',
                'Less: unpaid bills', 'Bill due 28 Oct 2026', 'final bill', 'Less: damage (1 item)', 'Adhesive hook marks',
                'Refund to tenant', 'due by 4 Nov 2026', '₱2,000.00'])
            ->assertSee('Approve settlement (Owner)')
            ->assertSee('Print');
    }

    // ---------- Excess flow ----------

    public function test_excess_is_billed_and_paying_it_settles_the_move_out(): void
    {
        $this->billsUntil('2026-10-25', payLast: false);   // the 25 Oct bill (3,700.00) stays unpaid
        $this->travelTo(Carbon::parse('2026-10-28'));
        $this->notice('2026-09-25', '2026-10-28');
        $this->recordActual('2026-09-25', '2026-10-28', '2026-10-28');
        $this->finalBill();                                 // 200.00
        $this->item('Broken cabinet door', '600.00');

        // 2,500.00 − (3,700.00 + 200.00) − 600.00 = −2,000.00 -> excess 2,000.00 (25 Oct bill: still within grace on 28 Oct).
        $this->actingAs($this->owner)->patch(route('moveouts.approve', $this->moveout()))->assertSessionHasNoErrors();

        $m = $this->moveout();
        $this->assertSame('Excess billed', $m->status);
        $this->assertSame('2000.00', $m->excess_amount);
        $excess = $m->excessBill;
        $this->assertSame('0.00', $excess->rent_amount);
        $this->assertSame('2000.00', $excess->other_charges);
        $this->assertSame('2000.00', $excess->total_amount);
        $this->assertSame('2026-11-04', $excess->due_date->toDateString());
        $this->assertSame(0, Bill::where('lease_id', $this->lease->lease_id)->where('status', '!=', 'Paid')->where('bill_id', '!=', $excess->bill_id)->count(),
            'Older bills closed at approval');

        $this->actingAs($this->manager)->delete(route('bills.destroy', $excess))->assertSessionHasErrors('bill');

        $this->actingAs($this->manager)->post(route('payments.store'), ['bill_id' => $excess->bill_id, 'payment_date' => '2026-10-28',
            'mode' => 'Cash', 'amount' => '2000.00'])->assertSessionHasNoErrors();

        $this->assertSame('Settled', $m->refresh()->status);
        $this->assertSame('Ended', $this->lease->refresh()->status);
        $this->assertSame('Vacant', $this->room->refresh()->status);

        $this->actingAs($this->owner)->delete(route('payments.destroy', Payment::where('bill_id', $excess->bill_id)->first()))->assertForbidden();
    }

    // ---------- Early departure ----------

    public function test_early_departure_forfeits_the_refund_and_settles_directly(): void
    {
        $this->billsUntil('2026-09-25');
        $this->travelTo(Carbon::parse('2026-10-10'));
        $this->notice('2026-09-15', '2026-10-10');
        $this->recordActual('2026-09-15', '2026-10-10', '2026-10-10');

        // The 25 Oct bill is after the move-out: no longer offered in Billing.
        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $this->lease->lease_id]))->assertRedirect(route('bills.index'));

        $this->finalBill();                                 // 25 Sep – 10 Oct: 200.00
        $this->actingAs($this->owner)->patch(route('moveouts.approve', $this->moveout()))->assertSessionHasNoErrors();

        $m = $this->moveout();
        $this->assertTrue($m->early_departure);
        $this->assertSame('0.00', $m->refund_amount, '2,300.00 left, but forfeited');
        $this->assertSame('0.00', $m->excess_amount);
        $this->assertSame('Settled', $m->status, 'Nothing to refund or collect: settled at approval');
        $this->assertSame('Ended', $this->lease->refresh()->status);
    }

    // ---------- Order of steps ----------

    public function test_regular_bills_first_then_final_bill_then_compute(): void
    {
        $this->billsUntil('2026-09-25');
        $this->travelTo(Carbon::parse('2026-10-28'));
        $this->notice('2026-09-25', '2026-10-28');
        $this->recordActual('2026-09-25', '2026-10-28', '2026-10-28');

        // Compute needs the final bill; the final bill needs the 25 Oct regular bill first.
        $this->actingAs($this->manager)->post(route('moveouts.compute', $this->moveout()))->assertSessionHasErrors('moveout');
        $this->actingAs($this->manager)->get(route('moveouts.final-bill.create', $this->moveout()))->assertRedirect(route('moveouts.show', $this->moveout()));
        $this->actingAs($this->manager)->post(route('moveouts.final-bill.store', $this->moveout()), [])->assertSessionHasErrors('final');

        $this->actingAs($this->manager)->get(route('moveouts.show', $this->moveout()))->assertSee('First generate the regular bill due 25 Oct 2026');
    }

    public function test_metered_room_final_bill_uses_the_final_reading(): void
    {
        // Aircon room, move-in reading 1,000.00; first bill due 25 Jul read at 1,100.00.
        $this->lease = $this->makeLease($this->makeRoom('209', 'Aircon'), $this->manager, null, 'Sample Tenant B', 1000.00);
        $this->travelTo(Carbon::parse('2026-07-23'));
        app(BillingService::class)->generate($this->lease, ['previous_reading' => 1000, 'current_reading' => 1100,
            'reading_date' => '2026-07-23', 'source' => 'Meter'], '2026-07-25', $this->manager);

        $this->travelTo(Carbon::parse('2026-08-10'));
        $this->notice('2026-07-25', '2026-08-10');
        $this->recordActual('2026-07-25', '2026-08-10', '2026-08-10');

        $this->actingAs($this->manager)->get(route('moveouts.final-bill.create', $this->moveout()))
            ->assertOk()->assertSee('value="1100.00"', false);

        // Final reading 1,150.00: 50 kWh × 15.00 = 750.00; water 200.00; rent 0.00.
        $this->actingAs($this->manager)->post(route('moveouts.final-bill.store', $this->moveout()), [
            'previous_reading' => '1100', 'current_reading' => '1150', 'reading_date' => '2026-08-10', 'source' => 'Meter',
        ])->assertSessionHasNoErrors();

        $final = $this->moveout()->finalBill;
        $this->assertSame('750.00', $final->electricity_amount);
        $this->assertSame('200.00', $final->water_amount);
        $this->assertSame('0.00', $final->rent_amount);
        $this->assertSame('950.00', $final->total_amount);
        $this->assertNotNull($final->reading_id);

        $this->actingAs($this->manager)->get(route('bills.show', $final))
            ->assertSee('Final billing statement')->assertSee('this month\'s rent was already charged ahead', false);
    }

    // ---------- Access ----------

    public function test_caretaker_cannot_use_move_outs(): void
    {
        $this->notice('2026-07-01', '2026-08-01');
        $caretaker = User::factory()->role('Caretaker')->create();

        $this->actingAs($caretaker)->get(route('moveouts.index'))->assertForbidden();
        $this->actingAs($caretaker)->get(route('moveouts.show', $this->moveout()))->assertForbidden();
        $this->actingAs($caretaker)->get(route('moveouts.create', ['lease' => $this->lease->lease_id]))->assertForbidden();
        $this->actingAs($caretaker)->patch(route('moveouts.approve', $this->moveout()))->assertForbidden();
    }
}
