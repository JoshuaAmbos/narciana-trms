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
            'category' => 'Damage', 'priority' => 'Routine', 'assigned_to' => $this->manager->user_id, 'status' => 'Resolved',
            'resolution' => 'Repaired', 'charged_to_tenant' => true, 'resolved_at' => '2026-07-02 10:00',
        ]);
    }

    private function generate(array $concernIds = []): TestResponse
    {
        return $this->actingAs($this->manager)->post(route('bills.store'), [
            'lease_id' => $this->lease->lease_id, 'due_date' => '2026-07-25', 'concerns' => $concernIds,
        ]);
    }

    public function test_chargeable_concerns_are_offered_but_never_ticked(): void
    {
        $this->concern(['description' => 'Not charged', 'cost' => 100, 'charged_to_tenant' => false]);
        $this->concern(['description' => 'Still open', 'cost' => 100, 'status' => 'Open', 'resolved_at' => null]);

        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $this->lease->lease_id]))
            ->assertOk()
            ->assertSee('Broken door knob')->assertSee('Stained wall')
            ->assertDontSee('Not charged')->assertDontSee('Still open')
            ->assertDontSee('checked', false);

        $this->generate()->assertSessionHasNoErrors();

        $bill = Bill::firstOrFail();
        $this->assertSame('0.00', $bill->other_charges, 'Never added automatically');
        $this->assertSame('3700.00', $bill->total_amount);
        $this->assertNull($this->knob->refresh()->bill_id);
    }

    public function test_ticked_concerns_become_other_charges_and_statement_lines(): void
    {
        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $this->lease->lease_id, 'concerns' => [$this->knob->concern_id]]))
            ->assertSee('Broken door knob (Damage): ₱350.00')->assertSee('₱4,050.00');

        $this->generate([$this->knob->concern_id, $this->wall->concern_id])->assertSessionHasNoErrors();

        $bill = Bill::firstOrFail();
        $this->assertSame('950.00', $bill->other_charges);
        $this->assertSame('4650.00', $bill->total_amount);
        $this->assertSame($bill->bill_id, $this->knob->refresh()->bill_id);
        $this->assertSame($bill->bill_id, $this->wall->refresh()->bill_id);

        $this->actingAs($this->manager)->get(route('bills.show', $bill))
            ->assertSee('Broken door knob (Damage): ₱350.00')->assertSee('Stained wall (Damage): ₱600.00')->assertSee('₱950.00');
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

        $this->assertNull($this->knob->refresh()->bill_id);
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
        $this->assertSame($bill->bill_id, $this->knob->refresh()->bill_id);
    }
}
