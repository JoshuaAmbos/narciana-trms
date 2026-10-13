<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Setting;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/** Settings (Owner only): rates and rules. Starting values: water 200, appliance 200, kWh 15, grace 3, 1% per day, refund 7. */
class SettingsTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'water_rate' => '200.00', 'appliance_fee' => '200.00',
            'standard_basic_rent' => '3000.00', 'standard_aircon_rent' => '3600.00',
            'kwh_rate' => '15.00', 'grace_days' => '3',
            'penalty_basis' => 'Per day of rent', 'penalty_rate' => '1.00', 'refund_days' => '7',
        ], $overrides);
    }

    public function test_page_shows_current_values(): void
    {
        Setting::current()->update(['water_rate' => 225.50, 'grace_days' => 4]);

        $this->actingAs($this->owner)->get(route('settings.index'))->assertOk()
            ->assertSee('value="225.50"', false)
            ->assertSee('value="4"', false)
            ->assertSee('Per day of rent')
            ->assertDontSee('Fixed rules');
    }

    public function test_owner_saves_settings_and_the_change_is_audited_old_to_new(): void
    {
        $manager = User::factory()->role('Manager')->create();
        Setting::current()->update(['updated_by' => $manager->user_id]);

        $this->actingAs($this->owner)
            ->put(route('settings.update'), $this->form(['water_rate' => '250', 'grace_days' => '5', 'penalty_basis' => 'Flat % of total', 'penalty_rate' => '5']))
            ->assertRedirect(route('settings.index'))->assertSessionHasNoErrors();

        $s = Setting::current();
        $this->assertSame('250.00', $s->water_rate);
        $this->assertSame(5, $s->grace_days);
        $this->assertSame('Flat % of total', $s->penalty_basis);
        $this->assertSame('5.00', $s->penalty_rate);
        $this->assertSame($this->owner->user_id, $s->updated_by);

        $log = AuditLog::where('action', 'Settings changed')->sole();
        $this->assertSame($this->owner->user_id, $log->user_id);
        $this->assertSame('tbl_setting', $log->table_name);
        $this->assertSame($s->setting_id, $log->record_id);
        $this->assertSame(
            'Water rate (per occupant): ₱200.00 → ₱250.00; Grace period: 3 days → 5 days; Penalty basis: Per day of rent → Flat % of total; Penalty rate: 1.00% → 5.00%',
            $log->details,
        );
    }

    public function test_saving_without_changes_writes_no_audit_entry(): void
    {
        $this->actingAs($this->owner)->put(route('settings.update'), $this->form(['water_rate' => '200']))
            ->assertSessionHas('status', 'No changes to save.');

        $this->assertSame(0, AuditLog::where('action', 'Settings changed')->count());
    }

    public function test_invalid_values_are_rejected_and_nothing_changes(): void
    {
        $bad = [
            'water_rate' => '-1', 'appliance_fee' => '10000.01', 'kwh_rate' => 'abc', 'grace_days' => '2.5',
            'penalty_basis' => 'Per week', 'penalty_rate' => '101', 'refund_days' => '61',
        ];
        $this->actingAs($this->owner)->put(route('settings.update'), $this->form($bad))
            ->assertSessionHasErrors(array_keys($bad));

        $this->actingAs($this->owner)->put(route('settings.update'), $this->form(['water_rate' => '200.555', 'grace_days' => '-1', 'refund_days' => '']))
            ->assertSessionHasErrors(['water_rate', 'grace_days', 'refund_days']);

        $s = Setting::current();
        $this->assertSame('200.00', $s->water_rate);
        $this->assertSame(3, $s->grace_days);
        $this->assertSame(0, AuditLog::where('action', 'Settings changed')->count());
    }

    public function test_zero_and_upper_limits_are_allowed(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.update'), $this->form(['water_rate' => '0', 'penalty_rate' => '100', 'grace_days' => '31', 'refund_days' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', Setting::current()->water_rate);
    }

    /**
     * Basic room 204 (1 occupant) and aircon room 206 (1 occupant, move-in reading 100 kWh). First bills due 25 Jul 2026:
     *   204: 3,500 rent + 200 water = 3,700.00
     *   206: 3,500 rent + 200 water + 50 kWh × 15 = 4,450.00
     * Then water → 250 and kWh → 20. The July bills stay the same; August bills use the new rates:
     *   204: 3,500 + 250 = 3,750.00
     *   206: 3,500 + 250 + 20 kWh × 20 = 4,150.00
     */
    public function test_changing_rates_does_not_change_bills_already_generated(): void
    {
        $manager = User::factory()->role('Manager')->create();
        $basic = $this->makeLease($this->makeRoom('204'), $manager, null, 'Sample Tenant A');
        $aircon = $this->makeLease($this->makeRoom('206', 'Aircon'), $manager, null, 'Sample Tenant B', 100.0);
        $billing = app(BillingService::class);
        $reading = fn (float $prev, float $cur, string $date) => ['previous_reading' => $prev, 'current_reading' => $cur, 'reading_date' => $date, 'source' => 'Meter'];

        $this->travelTo(Carbon::parse('2026-07-20'));
        $julyBasic = $billing->generate($basic, null, '2026-07-25', $manager);
        $julyAircon = $billing->generate($aircon, $reading(100, 150, '2026-07-20'), '2026-07-25', $manager);
        $this->assertSame('3700.00', $julyBasic->total_amount);
        $this->assertSame('4450.00', $julyAircon->total_amount);

        $this->actingAs($this->owner)->put(route('settings.update'), $this->form(['water_rate' => '250', 'kwh_rate' => '20']))
            ->assertSessionHasNoErrors();

        $julyBasic = Bill::find($julyBasic->bill_id);
        $julyAircon = Bill::with('meterReading')->find($julyAircon->bill_id);
        $this->assertSame('200.00', $julyBasic->water_amount);
        $this->assertSame('3700.00', $julyBasic->total_amount);
        $this->assertSame('200.00', $julyAircon->water_amount);
        $this->assertSame('750.00', $julyAircon->electricity_amount);
        $this->assertSame('15.00', $julyAircon->meterReading->rate_per_kwh);
        $this->assertSame('4450.00', $julyAircon->total_amount);
        $this->actingAs($manager)->get(route('bills.show', $julyBasic))->assertOk()->assertSee('₱3,700.00');

        $this->travelTo(Carbon::parse('2026-08-20'));
        $augBasic = $billing->generate($basic, null, '2026-08-25', $manager);
        $augAircon = $billing->generate($aircon, $reading(150, 170, '2026-08-20'), '2026-08-25', $manager);
        $this->assertSame('250.00', $augBasic->water_amount);
        $this->assertSame('3750.00', $augBasic->total_amount);
        $this->assertSame('400.00', $augAircon->electricity_amount);
        $this->assertSame('4150.00', $augAircon->total_amount);
    }

    public function test_owner_updates_default_room_capacity_ceiling_and_change_is_audited(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.update'), $this->form(['default_room_capacity' => 3]))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, Setting::current()->default_room_capacity);

        $log = AuditLog::where('action', 'Settings changed')->sole();
        $this->assertSame('Default room capacity: 2 occupants → 3 occupants', $log->details);
    }

    public function test_settings_module_is_accessible_strictly_to_owner(): void
    {
        foreach (['Manager', 'Caretaker'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
            $this->actingAs($user)->put(route('settings.update'), $this->form())->assertForbidden();
        }
    }
}
