<?php

namespace Tests\Feature;

use App\Models\Appliance;
use App\Models\Bill;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Occupant;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/**
 * Leases in these tests move in on 25 Jun 2026 with due day 25:
 * first bill due 25 Jul 2026 (period 25 Jun – 24 Jul), next due 25 Aug 2026 (period 25 Jul – 24 Aug).
 */
class BillingTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->role('Owner')->create();
        $this->settings($owner);
        $this->manager = User::factory()->role('Manager')->create();
        $this->travelTo(Carbon::parse('2026-07-20 09:00')); // 5 days before the first due date
    }

    private function generate(Lease $lease, array $reading = []): \Illuminate\Testing\TestResponse
    {
        $due = app(\App\Services\BillingService::class)->nextDueDate($lease)->toDateString();

        return $this->actingAs($this->manager)->post(route('bills.store'), ['lease_id' => $lease->lease_id, 'due_date' => $due] + $reading);
    }

    private function reading(float $previous, float $current): array
    {
        return ['previous_reading' => $previous, 'current_reading' => $current, 'reading_date' => '2026-07-20', 'source' => 'Meter'];
    }

    // ---------- To bill ----------

    public function test_lease_appears_to_bill_seven_days_before_the_due_date(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);

        $this->travelTo(Carbon::parse('2026-07-17'));
        $this->actingAs($this->manager)->get(route('bills.index'))->assertDontSee(route('bills.create', ['lease' => $lease->lease_id]), false);

        $this->travelTo(Carbon::parse('2026-07-18'));
        $this->actingAs($this->manager)->get(route('bills.index'))->assertSee(route('bills.create', ['lease' => $lease->lease_id]), false);
    }

    // ---------- Room without a meter ----------

    public function test_basic_room_bill_with_water_and_appliances(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager, 'Sample Co-tenant');
        foreach (['Rice cooker' => ['2026-06-25', null], 'Kettle' => ['2026-07-10', null], 'Old fan' => ['2026-06-01', '2026-06-20']] as $name => [$added, $removed]) {
            Appliance::create(['lease_id' => $lease->lease_id, 'appliance_name' => $name, 'monthly_fee' => 200, 'date_added' => $added,
                'date_removed' => $removed, 'source' => 'Declared', 'user_id' => $this->manager->user_id]);
        }

        $this->generate($lease)->assertSessionHasNoErrors();

        $bill = Bill::firstOrFail();
        $this->assertSame('2026-06-25', $bill->period_start->toDateString());
        $this->assertSame('2026-07-25', $bill->due_date->toDateString());
        $this->assertSame('3500.00', $bill->rent_amount);
        $this->assertSame('400.00', $bill->water_amount, '2 occupants × 200');
        $this->assertSame('400.00', $bill->appliance_amount, '2 appliances in the period (the fan was removed before it)');
        $this->assertSame('0.00', $bill->electricity_amount);
        $this->assertSame('0.00', $bill->penalty_amount);
        $this->assertSame('0.00', $bill->other_charges);
        $this->assertSame('4300.00', $bill->total_amount);
        $this->assertSame('Unpaid', $bill->status);
        $this->assertNull($bill->reading_id);
        $this->assertSame($this->manager->user_id, $bill->user_id);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Bill generated', 'table_name' => 'tbl_bill', 'record_id' => $bill->bill_id]);
    }

    public function test_water_counts_occupants_staying_at_any_time_in_the_period(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager, 'Sample Co-tenant');
        // Co-tenant left on 5 Jul (inside the period): still counted.
        $lease->occupants()->where('is_principal', false)->update(['date_removed' => '2026-07-05']);
        // Someone who only arrives after the period ends: not counted.
        Occupant::create(['lease_id' => $lease->lease_id, 'full_name' => 'Sample Later', 'is_principal' => false, 'date_added' => '2026-07-25']);

        $this->generate($lease)->assertSessionHasNoErrors();

        $this->assertSame('400.00', Bill::first()->water_amount);
    }

    public function test_bills_follow_in_order_month_after_month(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->generate($lease)->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-08-20'));
        $this->generate($lease)->assertSessionHasNoErrors();

        $second = Bill::orderByDesc('due_date')->first();
        $this->assertSame('2026-07-25', $second->period_start->toDateString());
        $this->assertSame('2026-08-25', $second->due_date->toDateString());
    }

    public function test_the_same_month_cannot_be_billed_twice(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->generate($lease)->assertSessionHasNoErrors();

        // Double submit of the same form (same due date).
        $this->actingAs($this->manager)->post(route('bills.store'), ['lease_id' => $lease->lease_id, 'due_date' => '2026-07-25'])
            ->assertSessionHasErrors('lease');

        $this->assertSame(1, Bill::count());
    }

    public function test_too_early_is_refused(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->travelTo(Carbon::parse('2026-07-10'));

        $this->generate($lease)->assertSessionHasErrors('lease');
        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $lease->lease_id]))->assertRedirect(route('bills.index'));
        $this->assertSame(0, Bill::count());
    }

    // ---------- Room with its own meter ----------

    public function test_metered_room_bill_uses_the_reading_and_no_appliances(): void
    {
        $lease = $this->makeLease($this->makeRoom('209', 'Aircon', ['monthly_rent' => 3600]), $this->manager, 'Sample Co-tenant', 'Sample Tenant A', 1842.00);
        $lease->update(['monthly_rent' => 3600]);
        Appliance::create(['lease_id' => $lease->lease_id, 'appliance_name' => 'Kettle', 'monthly_fee' => 200, 'date_added' => '2026-06-25',
            'source' => 'Declared', 'user_id' => $this->manager->user_id]);

        // The form suggests the move-in reading as the previous reading.
        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $lease->lease_id]))
            ->assertOk()->assertSee('value="1842.00"', false);

        $this->generate($lease, $this->reading(1842.00, 1950.00))->assertSessionHasNoErrors();

        $bill = Bill::firstOrFail();
        $this->assertSame('1620.00', $bill->electricity_amount, '108 kWh × 15.00');
        $this->assertSame('0.00', $bill->appliance_amount);
        $this->assertSame('5620.00', $bill->total_amount);

        $reading = MeterReading::findOrFail($bill->reading_id);
        $this->assertSame('1950.00', $reading->current_reading);
        $this->assertSame('15.00', $reading->rate_per_kwh);
        $this->assertSame($lease->room_id, $reading->room_id);
    }

    public function test_next_bill_suggests_the_last_current_reading(): void
    {
        $lease = $this->makeLease($this->makeRoom('209', 'Aircon'), $this->manager, null, 'Sample Tenant A', 1000.00);
        $this->generate($lease, $this->reading(1000, 1100))->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2026-08-20'));
        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $lease->lease_id]))
            ->assertOk()->assertSee('value="1100.00"', false);
    }

    public function test_metered_room_needs_a_valid_reading(): void
    {
        $lease = $this->makeLease($this->makeRoom('209', 'Aircon'), $this->manager);

        $this->generate($lease)->assertSessionHasErrors(['previous_reading', 'current_reading', 'reading_date', 'source']);
        $this->generate($lease, $this->reading(1950, 1842))
            ->assertSessionHasErrors(['current_reading' => 'The current reading cannot be lower than the previous reading.']);
        $this->generate($lease, ['reading_date' => '2026-07-21'] + $this->reading(1, 2))->assertSessionHasErrors('reading_date');

        $this->assertSame(0, Bill::count());
        $this->assertSame(0, MeterReading::count(), 'Nothing is half-saved');
    }

    public function test_the_meter_setting_decides_not_the_room_type(): void
    {
        // A Basic room that the Owner marked as metered is billed per kWh.
        $lease = $this->makeLease($this->makeRoom('213', 'Basic', ['is_metered' => true]), $this->manager);

        $this->generate($lease)->assertSessionHasErrors('current_reading');
        $this->generate($lease, $this->reading(100, 110))->assertSessionHasNoErrors();

        $this->assertSame('150.00', Bill::first()->electricity_amount);
    }

    public function test_move_in_reading_on_the_moa_form_only_for_metered_rooms(): void
    {
        $aircon = $this->makeRoom('209', 'Aircon');
        $basic = $this->makeRoom('204');

        $this->actingAs($this->manager)->post(route('leases.store'), $this->moaForm($aircon, ['move_in_reading' => '1500.50']))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('leases.store'), $this->moaForm($basic, ['full_name' => 'Sample Tenant B', 'move_in_reading' => '99']))->assertSessionHasNoErrors();

        $this->assertSame(1, MeterReading::count());
        $reading = MeterReading::first();
        $this->assertSame($aircon->room_id, $reading->room_id);
        $this->assertSame('1500.50', $reading->previous_reading);
        $this->assertSame('1500.50', $reading->current_reading);
        $this->assertSame('2026-06-25', $reading->reading_date->toDateString());
    }

    // ---------- Statement ----------

    public function test_statement_shows_every_component_and_the_reading(): void
    {
        $lease = $this->makeLease($this->makeRoom('209', 'Aircon'), $this->manager, 'Sample Co-tenant', 'Sample Tenant A', 1842.00);
        $this->generate($lease, $this->reading(1842, 1950));

        $this->actingAs($this->manager)->get(route('bills.show', Bill::first()))
            ->assertOk()
            ->assertSeeInOrder(['Monthly rent', 'Water (2 occupants × ₱200.00)', 'Electricity (108.00 kWh × ₱15.00)',
                'Reading 1,842.00 → 1,950.00 kWh', 'Appliance surcharges', 'Penalty', 'Other charges', 'Total due on 25 Jul 2026'])
            ->assertSee('Print / Save as PDF');
    }

    public function test_preview_before_saving(): void
    {
        $lease = $this->makeLease($this->makeRoom('209', 'Aircon'), $this->manager, null, 'Sample Tenant A', 1000.00);

        $this->actingAs($this->manager)->get(route('bills.create', ['lease' => $lease->lease_id] + $this->reading(1000, 1010)))
            ->assertOk()->assertSee('Electricity (10.00 kWh × ₱15.00)')->assertSee('Save bill');

        $this->assertSame(0, Bill::count(), 'Preview saves nothing');
    }

    // ---------- Deleting ----------

    public function test_unpaid_bill_can_be_deleted_with_its_reading_and_generated_again(): void
    {
        $lease = $this->makeLease($this->makeRoom('209', 'Aircon'), $this->manager, null, 'Sample Tenant A', 1000.00);
        $this->generate($lease, $this->reading(1000, 9999))->assertSessionHasNoErrors(); // wrong reading
        $bill = Bill::firstOrFail();

        $this->actingAs($this->manager)->delete(route('bills.destroy', $bill))->assertRedirect(route('bills.index', ['month' => '2026-07']));

        $this->assertSame(0, Bill::count());
        $this->assertSame(1, MeterReading::count(), 'Only the move-in reading is left');
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Bill deleted', 'record_id' => $bill->bill_id]);

        $this->generate($lease, $this->reading(1000, 1100))->assertSessionHasNoErrors();
        $this->assertSame('2026-07-25', Bill::first()->due_date->toDateString());
    }

    public function test_paid_bill_or_bill_with_payment_cannot_be_deleted(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->generate($lease);
        $bill = Bill::firstOrFail();
        Payment::create(['bill_id' => $bill->bill_id, 'amount' => $bill->total_amount, 'payment_date' => '2026-07-20',
            'mode' => 'Cash', 'ack_no' => 'ACK-2026-0001', 'user_id' => $this->manager->user_id]);

        $this->actingAs($this->manager)->get(route('bills.show', $bill))->assertDontSee('Delete bill');
        $this->actingAs($this->manager)->delete(route('bills.destroy', $bill))->assertSessionHasErrors('bill');
        $this->assertSame(1, Bill::count());
    }

    public function test_only_the_newest_bill_can_be_deleted(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->generate($lease);
        $this->travelTo(Carbon::parse('2026-08-20'));
        $this->generate($lease);
        $older = Bill::orderBy('due_date')->first();

        $this->actingAs($this->manager)->delete(route('bills.destroy', $older))->assertSessionHasErrors('bill');
        $this->assertSame(2, Bill::count());
    }

    // ---------- Access ----------

    public function test_caretaker_cannot_use_billing(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->generate($lease);
        $bill = Bill::firstOrFail();
        $caretaker = User::factory()->role('Caretaker')->create();

        $this->actingAs($caretaker)->get(route('bills.index'))->assertForbidden();
        $this->actingAs($caretaker)->get(route('bills.create', ['lease' => $lease->lease_id]))->assertForbidden();
        $this->actingAs($caretaker)->post(route('bills.store'), ['lease_id' => $lease->lease_id])->assertForbidden();
        $this->actingAs($caretaker)->get(route('bills.show', $bill))->assertForbidden();
        $this->actingAs($caretaker)->delete(route('bills.destroy', $bill))->assertForbidden();
        $this->actingAs($caretaker)->get(route('meter-readings.index'))->assertForbidden();
        $this->actingAs($caretaker)->get('/')->assertDontSee('Billing');
    }

    public function test_owner_can_bill_too(): void
    {
        $owner = User::where('role', 'Owner')->first();
        $lease = $this->makeLease($this->makeRoom('204'), $owner);

        $this->actingAs($owner)->get(route('bills.index'))->assertOk()->assertSee('Generate');
    }
}
