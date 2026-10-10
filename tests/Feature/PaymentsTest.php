<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/**
 * Lease: Basic room, rent 3,500.00, 1 occupant, move-in 25 Jun 2026, due day 25.
 * First bill due 25 Jul 2026 = 3,500.00 rent + 200.00 water = 3,700.00. Grace 3 days: on time until 28 Jul.
 */
class PaymentsTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private User $manager;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
        $this->manager = User::factory()->role('Manager')->create();

        $this->travelTo(Carbon::parse('2026-07-20'));
        $this->lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->bill(); // first bill, due 25 Jul
    }

    private function bill(): Bill
    {
        $billing = app(BillingService::class);

        return $billing->generate($this->lease, null, $billing->nextDueDate($this->lease)->toDateString(), $this->manager);
    }

    private function pay(Bill $bill, array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->manager)->post(route('payments.store'), array_merge([
            'bill_id' => $bill->bill_id, 'payment_date' => today()->toDateString(), 'mode' => 'Cash', 'amount' => '3700.00',
        ], $overrides));
    }

    private function firstBill(): Bill
    {
        return Bill::orderBy('due_date')->firstOrFail();
    }

    // ---------- Recording ----------

    public function test_on_time_cash_payment(): void
    {
        $this->travelTo(Carbon::parse('2026-07-26')); // after the due date but within the grace period
        $bill = $this->firstBill();

        $response = $this->pay($bill)->assertSessionHasNoErrors();

        $payment = Payment::firstOrFail();
        $response->assertRedirect(route('payments.show', $payment));
        $this->assertSame('ACK-2026-0001', $payment->ack_no);
        $this->assertSame('3700.00', $payment->amount);
        $this->assertSame('Cash', $payment->mode);
        $this->assertNull($payment->gcash_ref_no);
        $this->assertSame($this->manager->user_id, $payment->user_id, 'Received by the logged-in user');

        $bill->refresh();
        $this->assertSame('Paid', $bill->status);
        $this->assertSame('0.00', $bill->penalty_amount);
        $this->assertSame('3700.00', $bill->total_amount);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Payment recorded', 'table_name' => 'tbl_payment', 'record_id' => $payment->payment_id]);
    }

    public function test_late_payment_adds_the_penalty_and_needs_the_exact_amount(): void
    {
        $this->travelTo(Carbon::parse('2026-07-30')); // 2 days after the grace period: 3,500 × 1% × 2 = 70.00
        $bill = $this->firstBill();

        $this->pay($bill, ['amount' => '3700.00'])
            ->assertSessionHasErrors(['amount' => 'The amount must be exactly ₱3,770.00 (no partial payments).']);
        $this->assertSame(0, Payment::count());
        $this->assertSame('0.00', $bill->refresh()->penalty_amount, 'Nothing saved on a refused payment');

        $this->pay($bill, ['amount' => '3770.00'])->assertSessionHasNoErrors();

        $bill->refresh();
        $this->assertSame('70.00', $bill->penalty_amount);
        $this->assertSame('3770.00', $bill->total_amount);
        $this->assertSame('Paid', $bill->status);
        $this->assertSame('3770.00', Payment::first()->amount);
    }

    public function test_penalty_uses_the_payment_date_not_today(): void
    {
        $this->travelTo(Carbon::parse('2026-07-30'));

        $this->pay($this->firstBill(), ['payment_date' => '2026-07-27', 'amount' => '3700.00'])->assertSessionHasNoErrors();
        $this->assertSame('0.00', $this->firstBill()->penalty_amount);
    }

    public function test_future_payment_date_is_refused(): void
    {
        $this->pay($this->firstBill(), ['payment_date' => '2026-07-21'])->assertSessionHasErrors('payment_date');
    }

    public function test_flat_percent_basis_from_settings(): void
    {
        Setting::first()->update(['penalty_basis' => 'Flat % of total', 'penalty_rate' => 2]);
        $this->travelTo(Carbon::parse('2026-08-10')); // late: 2% of 3,700.00 = 74.00

        $this->pay($this->firstBill(), ['amount' => '3774.00'])->assertSessionHasNoErrors();
        $this->assertSame('74.00', $this->firstBill()->penalty_amount);
    }

    public function test_gcash_needs_a_unique_13_digit_reference(): void
    {
        $bill = $this->firstBill();

        $this->pay($bill, ['mode' => 'GCash'])->assertSessionHasErrors(['gcash_ref_no' => 'Enter the GCash reference number.']);
        $this->pay($bill, ['mode' => 'GCash', 'gcash_ref_no' => '12345'])->assertSessionHasErrors('gcash_ref_no');

        $this->pay($bill, ['mode' => 'GCash', 'gcash_ref_no' => '5012 345 678901'], $this->owner)->assertSessionHasNoErrors();
        $this->assertSame('5012345678901', Payment::first()->gcash_ref_no, 'Spaces are removed');
        $this->assertSame($this->owner->user_id, Payment::first()->user_id);

        // Same reference on the next bill: refused.
        $this->travelTo(Carbon::parse('2026-08-20'));
        $next = $this->bill();
        $this->pay($next, ['mode' => 'GCash', 'gcash_ref_no' => '5012345678901', 'amount' => $next->total_amount])
            ->assertSessionHasErrors(['gcash_ref_no' => 'This GCash reference number was already used for another payment.']);
    }

    public function test_cash_payment_ignores_a_reference_number(): void
    {
        $this->pay($this->firstBill(), ['gcash_ref_no' => '5012345678901'])->assertSessionHasNoErrors();
        $this->assertNull(Payment::first()->gcash_ref_no);
    }

    public function test_oldest_bill_must_be_paid_first(): void
    {
        $this->travelTo(Carbon::parse('2026-08-20'));
        $second = $this->bill();

        $this->pay($second, ['amount' => $second->total_amount])->assertSessionHasErrors(['bill' => 'Pay the older bill first (due 25 Jul 2026).']);
        $this->actingAs($this->manager)->get(route('payments.create', ['bill' => $second->bill_id]))->assertRedirect(route('payments.index'));
        $this->assertSame(0, Payment::count());
    }

    public function test_a_paid_bill_cannot_be_paid_again(): void
    {
        $bill = $this->firstBill();
        $this->pay($bill)->assertSessionHasNoErrors();

        $this->pay($bill)->assertSessionHasErrors(['bill' => 'This bill is already paid.']);
        $this->assertSame(1, Payment::count());
    }

    // ---------- ACK numbers ----------

    public function test_ack_numbers_follow_each_other_and_restart_each_year(): void
    {
        $this->pay($this->firstBill())->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-08-20'));
        $second = $this->bill();
        $this->pay($second, ['amount' => $second->total_amount])->assertSessionHasNoErrors();

        $this->assertSame(['ACK-2026-0001', 'ACK-2026-0002'], Payment::orderBy('payment_id')->pluck('ack_no')->all());
        $this->assertSame('ACK-2027-0001', app(PaymentService::class)->nextAckNo(2027));
    }

    // ---------- Void (Owner only) ----------

    public function test_owner_can_void_and_the_ack_number_is_never_reused(): void
    {
        $this->travelTo(Carbon::parse('2026-07-30'));
        $bill = $this->firstBill();
        $this->pay($bill, ['amount' => '3770.00'])->assertSessionHasNoErrors();
        $payment = Payment::firstOrFail();

        $this->actingAs($this->manager)->delete(route('payments.destroy', $payment))->assertForbidden();

        $this->actingAs($this->owner)->delete(route('payments.destroy', $payment))->assertRedirect(route('bills.show', $bill));

        $bill->refresh();
        $this->assertSame(0, Payment::count());
        $this->assertSame('0.00', $bill->penalty_amount);
        $this->assertSame('3700.00', $bill->total_amount);
        $this->assertSame('Overdue', $bill->status, 'Unpaid again, and past the grace period');
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Payment voided (ACK-2026-0001)', 'user_id' => $this->owner->user_id]);

        $this->pay($bill, ['amount' => '3770.00'])->assertSessionHasNoErrors();
        $this->assertSame('ACK-2026-0002', Payment::first()->ack_no);
    }

    public function test_only_the_newest_paid_bill_can_be_voided(): void
    {
        $this->pay($this->firstBill())->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-08-20'));
        $second = $this->bill();
        $this->pay($second, ['amount' => $second->total_amount])->assertSessionHasNoErrors();
        $older = Payment::orderBy('payment_id')->first();

        $this->actingAs($this->owner)->get(route('payments.show', $older))->assertDontSee('Void this payment');
        $this->actingAs($this->owner)->delete(route('payments.destroy', $older))->assertForbidden();
        $this->assertSame(2, Payment::count());
    }

    // ---------- Overdue ----------

    public function test_bill_becomes_overdue_after_due_date_plus_grace(): void
    {
        $bill = $this->firstBill();

        $this->travelTo(Carbon::parse('2026-07-28'));
        app(PaymentService::class)->refreshOverdue();
        $this->assertSame('Unpaid', $bill->refresh()->status);

        $this->travelTo(Carbon::parse('2026-07-29'));
        $this->artisan('bills:mark-overdue')->assertSuccessful();
        $this->assertSame('Overdue', $bill->refresh()->status);
    }

    public function test_payments_page_shows_overdue_with_the_penalty_so_far(): void
    {
        $this->travelTo(Carbon::parse('2026-07-31')); // 3 days late: 105.00

        $this->actingAs($this->manager)->get(route('payments.index'))
            ->assertOk()->assertSee('Overdue 3 days')->assertSee('₱105.00')->assertSee('₱3,805.00');
        $this->assertSame('Overdue', $this->firstBill()->status, 'Refreshed when the page opens');

        $this->actingAs($this->manager)->get(route('bills.show', $this->firstBill()))->assertSee('Penalty so far');
    }

    // ---------- Collections and acknowledgment ----------

    public function test_monthly_collections_split_cash_and_gcash(): void
    {
        $other = $this->makeLease($this->makeRoom('205'), $this->manager, null, 'Sample Tenant B');
        $billing = app(BillingService::class);
        $otherBill = $billing->generate($other, null, $billing->nextDueDate($other)->toDateString(), $this->manager);

        $this->pay($this->firstBill())->assertSessionHasNoErrors();                                       // cash 3,700.00
        $this->pay($otherBill, ['mode' => 'GCash', 'gcash_ref_no' => '1234567890123', 'amount' => '3700.00'], $this->owner); // GCash 3,700.00

        $this->actingAs($this->owner)->get(route('payments.index', ['month' => '2026-07']))
            ->assertOk()
            ->assertSee('Collections, July 2026')
            ->assertSeeInOrder(['₱7,400.00', 'Collected in July 2026', '₱3,700.00', 'Cash', '₱3,700.00', 'GCash'])
            ->assertSee('ACK-2026-0001')->assertSee('ACK-2026-0002')
            ->assertSee('Manager ₱3,700.00')->assertSee('Owner ₱3,700.00');
    }

    public function test_acknowledgment_is_printable_and_complete(): void
    {
        $this->travelTo(Carbon::parse('2026-07-30'));
        $this->pay($this->firstBill(), ['amount' => '3770.00', 'mode' => 'GCash', 'gcash_ref_no' => '5012345678901']);

        $this->actingAs($this->manager)->get(route('payments.show', Payment::first()))
            ->assertOk()
            ->assertSee('ACK-2026-0001')->assertSee('30 July 2026')->assertSee('Sample Tenant A, Room 204')
            ->assertSee('2 days late')->assertSee('GCash (ref. 5012345678901)')->assertSee('Received by')
            ->assertSee('₱3,770.00')->assertSee('Print / Save as PDF');
    }

    // ---------- Access ----------

    public function test_caretaker_cannot_use_payments(): void
    {
        $caretaker = User::factory()->role('Caretaker')->create();
        $bill = $this->firstBill();
        $this->pay($bill);

        $this->actingAs($caretaker)->get('/payments')->assertForbidden();
        $this->actingAs($caretaker)->get('/billing')->assertForbidden();
        $this->actingAs($caretaker)->get(route('payments.index'))->assertForbidden();
        $this->actingAs($caretaker)->get(route('payments.create', ['bill' => $bill->bill_id]))->assertForbidden();
        $this->actingAs($caretaker)->post(route('payments.store'), ['bill_id' => $bill->bill_id])->assertForbidden();
        $this->actingAs($caretaker)->get(route('payments.show', Payment::first()))->assertForbidden();
        $this->actingAs($caretaker)->delete(route('payments.destroy', Payment::first()))->assertForbidden();
    }

    public function test_delinquency_breach_ui_and_modal_refactor(): void
    {
        $room = $this->makeRoom('208');
        $lease = $this->makeLease($room, $this->manager, null, 'Rodolfo Garcia');

        // Total deposit = 3500.00
        // Create 2 overdue bills totaling 4000.00 (> deposit)
        Bill::create([
            'lease_id' => $lease->lease_id,
            'period_start' => '2026-05-01',
            'due_date' => '2026-06-01',
            'rent_amount' => 2000.00,
            'total_amount' => 2000.00,
            'penalty_amount' => 0.00,
            'status' => 'Overdue',
            'user_id' => $this->manager->user_id,
        ]);
        Bill::create([
            'lease_id' => $lease->lease_id,
            'period_start' => '2026-06-01',
            'due_date' => '2026-07-01',
            'rent_amount' => 2000.00,
            'total_amount' => 2000.00,
            'penalty_amount' => 0.00,
            'status' => 'Overdue',
            'user_id' => $this->manager->user_id,
        ]);

        $this->travelTo(Carbon::parse('2026-07-15'));

        $response = $this->actingAs($this->manager)->get(route('payments.index'));
        $response->assertOk();

        // 1. Clean operational terminology
        $response->assertSee('Deposit Exhausted');
        $response->assertSee('1 Requires Attention');
        $response->assertSee('Room 208');
        $response->assertSee('Rodolfo Garcia');
        $response->assertSee('Exhausted');
        $response->assertDontSee('MOA Clause');
        $response->assertDontSee('MOA Clauses');
        $response->assertDontSee('Clause 8');

        // 2. Detail Modal presence and operational breakdown
        $response->assertSee('id="breachDetailModal"', false);
        $response->assertSee('Deposit Buffer Exhausted');
        $response->assertSee('Financial Breakdown');
        $response->assertSee('Unpaid Rent &amp; Utilities:', false);
        $response->assertSee('Total Outstanding Arrears:');
        $response->assertSee('Held Security Deposit:');
        $response->assertSee('Net Deficit:');
        $response->assertSee("This tenant's unpaid obligations have completely consumed their security deposit buffer. Continued non-payment leaves the account with zero security coverage.", false);
        $response->assertSee('Cancel / Close');
        $response->assertSee('View Lease Details');
        $response->assertSee('End Tenancy &amp; Vacate Room', false);

        // 3. Compact status presentation in the bills table without duplicate verbose banners
        $response->assertDontSee('[!] Breach: Deposit Exhausted (Eligible for Termination under MOA Clause 8)');

        // 4. Terminate via POST /leases/{lease}/terminate-breach route
        $terminateResponse = $this->actingAs($this->owner)
            ->post(route('leases.terminate-breach', $lease), [
                'termination_notes' => 'Terminated due to deposit buffer exhaustion.',
            ]);
        $terminateResponse->assertRedirect(route('leases.show', $lease));
        $this->assertSame('Cancelled', $lease->fresh()->status);
        $this->assertSame('Vacant', $room->fresh()->status);
    }
}
