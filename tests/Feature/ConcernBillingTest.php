<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Concern;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/**
 * Basic room, rent 3,500.00, 1 occupant; first bill due 25 Jul 2026 = 3,700.00 before other charges.
 * Charged concerns: door knob 350.00 and stained wall 600.00.
 */
class ConcernBillingTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private User $manager;

    private Lease $lease;

    private Concern $knob;

    private Concern $wall;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
        $this->manager = User::factory()->role('Manager')->create();
        $this->travelTo(Carbon::parse('2026-07-20'));
        $this->lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->knob = $this->concern(['description' => 'Broken door knob', 'cost' => 350]);
        $this->wall = $this->concern(['description' => 'Stained wall', 'cost' => 600]);
    }

    private function concern(array $attributes, ?Lease $lease = null): Concern
    {
        $lease ??= $this->lease;

        return Concern::create($attributes + [
            'room_id' => $lease->room_id, 'lease_id' => $lease->lease_id, 'reported_at' => '2026-07-01 10:00', 'channel' => 'Verbal',
            'category' => 'Damage to room or furniture', 'priority' => 'Routine', 'assigned_to' => $this->manager->user_id, 'status' => 'Resolved',
            'resolution' => 'Repaired', 'charged_to_tenant' => true, 'resolved_at' => '2026-07-02 10:00',
        ]);
    }

    private function generate(?array $concernIds = null): TestResponse
    {
        $payload = [
            'lease_id' => $this->lease->lease_id, 'due_date' => '2026-07-25',
        ];
        if ($concernIds !== null) {
            $payload['concerns'] = $concernIds;
        }

        return $this->actingAs($this->manager)->post(route('bills.store'), $payload);
    }

    public function test_chargeable_concerns_are_offered_and_checked_by_default(): void
    {
        $this->concern(['description' => 'Not charged', 'cost' => 100, 'charged_to_tenant' => false]);
        $this->concern(['description' => 'Still open', 'cost' => 100, 'status' => 'Open', 'resolved_at' => null]);

        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $this->lease->lease_id]))
            ->assertOk()
            ->assertSee('Broken door knob')->assertSee('Stained wall')
            ->assertDontSee('Not charged')->assertDontSee('Still open')
            ->assertSee('checked');

        // Generating the bill without explicit concern IDs automatically recovers all unbilled chargeable concerns
        $this->generate()->assertSessionHasNoErrors();

        $bill = Bill::firstOrFail();
        $this->assertSame('950.00', $bill->other_charges, 'Automatically rolled into other_charges');
        $this->assertSame('4650.00', $bill->total_amount);
        $this->assertSame($bill->bill_id, $this->knob->refresh()->billed_in_bill_id);
        $this->assertSame($bill->bill_id, $this->knob->bill_id);
        $this->assertSame($bill->bill_id, $this->wall->refresh()->billed_in_bill_id);
        $this->assertSame($bill->bill_id, $this->wall->bill_id);
    }

    public function test_deselected_concerns_are_not_billed_when_explicitly_excluded(): void
    {
        $this->generate([])->assertSessionHasNoErrors();

        $bill = Bill::firstOrFail();
        $this->assertSame('0.00', $bill->other_charges);
        $this->assertSame('3700.00', $bill->total_amount);
        $this->assertNull($this->knob->refresh()->billed_in_bill_id);
        $this->assertNull($this->knob->bill_id);
    }

    public function test_ticked_concerns_become_other_charges_and_statement_lines(): void
    {
        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $this->lease->lease_id, 'concerns' => [$this->knob->concern_id]]))
            ->assertSee('Broken door knob (Damage to room or furniture): ₱350.00')->assertSee('₱4,050.00');

        $this->generate([$this->knob->concern_id, $this->wall->concern_id])->assertSessionHasNoErrors();

        $bill = Bill::firstOrFail();
        $this->assertSame('950.00', $bill->other_charges);
        $this->assertSame('4650.00', $bill->total_amount);
        $this->assertSame($bill->bill_id, $this->knob->refresh()->billed_in_bill_id);
        $this->assertSame($bill->bill_id, $this->wall->refresh()->billed_in_bill_id);

        $this->actingAs($this->manager)->get(route('bills.show', $bill))
            ->assertSee('Broken door knob (Damage to room or furniture): ₱350.00')->assertSee('Stained wall (Damage to room or furniture): ₱600.00')->assertSee('₱950.00');
    }

    public function test_concern_that_cannot_be_charged_is_refused(): void
    {
        $otherLease = $this->makeLease($this->makeRoom('205'), $this->manager, null, 'Sample Tenant B');
        $foreign = $this->concern(['description' => 'Other tenant', 'cost' => 100], $otherLease);
        $open = $this->concern(['description' => 'Open', 'cost' => 100, 'status' => 'Open', 'resolved_at' => null]);
        $free = $this->concern(['description' => 'Free', 'cost' => 100, 'charged_to_tenant' => false]);

        foreach ([$foreign, $open, $free] as $concern) {
            $this->generate([$concern->concern_id])->assertSessionHasErrors('concerns');
        }
        $this->assertSame(0, Bill::count());
    }

    public function test_a_concern_is_billed_only_once(): void
    {
        $this->generate([$this->knob->concern_id])->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-08-20'));
        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $this->lease->lease_id]))->assertDontSee('Broken door knob');
        $this->actingAs($this->manager)->post(route('bills.store'), ['lease_id' => $this->lease->lease_id, 'due_date' => '2026-08-25',
            'concerns' => [$this->knob->concern_id]])->assertSessionHasErrors('concerns');
    }

    public function test_deleting_the_bill_releases_its_concerns(): void
    {
        $this->generate([$this->knob->concern_id])->assertSessionHasNoErrors();
        $bill = Bill::firstOrFail();

        $this->actingAs($this->manager)->delete(route('bills.destroy', $bill))->assertSessionHasNoErrors();

        $this->assertNull($this->knob->refresh()->billed_in_bill_id);
        $this->assertNull($this->knob->bill_id);
        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $this->lease->lease_id]))->assertSee('Broken door knob');
    }

    public function test_voiding_the_payment_keeps_the_concerns_on_the_bill(): void
    {
        $this->generate([$this->knob->concern_id])->assertSessionHasNoErrors();
        $bill = Bill::firstOrFail();

        // The payment must include the other charges: 3,700.00 + 350.00.
        $this->actingAs($this->manager)->post(route('payments.store'), ['bill_id' => $bill->bill_id, 'payment_date' => '2026-07-20',
            'mode' => 'Cash', 'amount' => '3700.00'])->assertSessionHasErrors('amount');
        $this->actingAs($this->manager)->post(route('payments.store'), ['bill_id' => $bill->bill_id, 'payment_date' => '2026-07-20',
            'mode' => 'Cash', 'amount' => '4050.00'])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->delete(route('payments.destroy', Payment::first()))->assertSessionHasNoErrors();

        $bill->refresh();
        $this->assertSame('Unpaid', $bill->status);
        $this->assertSame('350.00', $bill->other_charges);
        $this->assertSame($bill->bill_id, $this->knob->refresh()->billed_in_bill_id);
        $this->assertSame($bill->bill_id, $this->knob->bill_id);
    }

    public function test_acceptance_criteria_end_to_end_tenant_chargeable_concern_cost_recovery(): void
    {
        // 1. Intake & Resolution: Resolve a concern ticket with repair_cost = 350.00 and charge_to_tenant = true
        $newRoom = $this->makeRoom('208');
        $newLease = $this->makeLease($newRoom, $this->manager, null, 'Test Tenant Repair');

        $response = $this->actingAs($this->manager)->post(route('concerns.store'), [
            'room_id' => $newRoom->room_id,
            'lease_id' => $newLease->lease_id,
            'category' => 'Damage to room or furniture',
            'reported_at' => '2026-07-10T10:00',
            'channel' => 'Verbal',
            'priority' => 'Routine',
            'description' => 'Window glass crack repair',
            'status' => 'Resolved',
            'resolution' => 'Replaced glass pane',
            'repair_cost' => '350.00',
            'charge_to_tenant' => '1',
            'assigned_to' => $this->manager->user_id,
        ]);
        $response->assertSessionHasNoErrors();

        $concern = Concern::where('description', 'Window glass crack repair')->firstOrFail();
        $this->assertSame('350.00', $concern->repair_cost);
        $this->assertSame('350.00', $concern->cost);
        $this->assertTrue((bool) $concern->charge_to_tenant);
        $this->assertTrue((bool) $concern->charged_to_tenant);
        $this->assertNull($concern->billed_in_bill_id);
        $this->assertNull($concern->bill_id);

        // 2. BillingService::calculateBillCharges consolidates rent + water + electricity + other_charges
        $billingService = app(BillingService::class);
        $calc = $billingService->calculateBillCharges($newLease);
        $this->assertEquals(350.00, $calc['other_charges']);
        $this->assertEquals(4050.00, $calc['total_amount']); // 3500 rent + 200 water + 350 repairs
        $this->assertTrue($calc['unbilled_concerns']->contains('concern_id', $concern->concern_id));

        // 3. BillingService::generate rolls the 350.00 into other_charges and locks billed_in_bill_id
        $bill = $billingService->generate($newLease, [
            'period_start' => '2026-06-25',
            'due_date' => '2026-07-25',
        ], $this->manager);

        $this->assertSame('350.00', $bill->other_charges);
        $this->assertSame('4050.00', $bill->total_amount);

        $concern->refresh();
        $this->assertSame($bill->bill_id, $concern->billed_in_bill_id);
        $this->assertSame($bill->bill_id, $concern->bill_id);

        // 4. Double billing prevention: subsequent check shows zero unbilled concerns
        $nextCalc = $billingService->calculateBillCharges($newLease);
        $this->assertEquals(0.00, $nextCalc['other_charges']);
        $this->assertTrue($nextCalc['unbilled_concerns']->isEmpty());

        // 5. Statement view displays the itemized repair description and amount
        $this->actingAs($this->manager)->get(route('bills.show', $bill))
            ->assertOk()
            ->assertSee('Tenant Repair: Window glass crack repair')
            ->assertSee('350.00');

        // 6. Payment recording requires full balance including the repair fee
        $this->actingAs($this->manager)->post(route('payments.store'), [
            'bill_id' => $bill->bill_id,
            'payment_date' => '2026-07-20',
            'mode' => 'Cash',
            'amount' => '3700.00', // Omitting repair cost
        ])->assertSessionHasErrors('amount');

        $this->actingAs($this->manager)->post(route('payments.store'), [
            'bill_id' => $bill->bill_id,
            'payment_date' => '2026-07-20',
            'mode' => 'Cash',
            'amount' => '4050.00', // Full balance
        ])->assertSessionHasNoErrors();

        $this->assertSame('Paid', $bill->refresh()->status);
    }
}
