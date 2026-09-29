<?php

namespace Tests\Feature;

use App\Models\Appliance;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private User $caretaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
        $this->caretaker = User::factory()->role('Caretaker')->create();
    }

    private function applianceForm(array $overrides = []): array
    {
        return array_merge(['appliance_name' => 'Rice cooker', 'source' => 'Declared', 'date_added' => today()->toDateString()], $overrides);
    }

    // ---------- Appliances ----------

    public function test_caretaker_can_add_an_appliance_to_a_room_without_a_meter_with_the_settings_fee(): void
    {
        Setting::first()->update(['appliance_fee' => 250]);
        $lease = $this->makeLease($this->makeRoom('201', 'Basic'), $this->owner);

        $this->actingAs($this->caretaker)
            ->from(route('leases.household', $lease))
            ->post(route('leases.appliances.store', $lease), $this->applianceForm(['source' => 'CCTV', 'monthly_fee' => '1']))
            ->assertRedirect(route('leases.household', $lease))
            ->assertSessionHas('status', 'Rice cooker was added (₱250.00 a month).');

        $appliance = Appliance::firstOrFail();
        $this->assertSame('250.00', $appliance->monthly_fee, 'Fee comes from Settings, not from the form');
        $this->assertSame('CCTV', $appliance->source);
        $this->assertSame($this->caretaker->user_id, $appliance->user_id);
    }

    public function test_appliances_are_refused_for_a_metered_room(): void
    {
        $lease = $this->makeLease($this->makeRoom('208', 'Aircon'), $this->owner); // is_metered = 1

        $this->actingAs($this->caretaker)->post(route('leases.appliances.store', $lease), $this->applianceForm())
            ->assertSessionHasErrorsIn('appliance', 'appliance_name');

        $this->assertSame(0, Appliance::count());
        $this->actingAs($this->caretaker)->get(route('leases.household', $lease))
            ->assertSee('has its own electric meter')->assertDontSee('+ Add appliance');
    }

    public function test_meter_setting_decides_not_the_room_type(): void
    {
        // A Basic room that the Owner marked as metered: no appliances.
        $lease = $this->makeLease($this->makeRoom('210', 'Basic', ['is_metered' => true]), $this->owner);

        $this->actingAs($this->caretaker)->post(route('leases.appliances.store', $lease), $this->applianceForm())
            ->assertSessionHasErrorsIn('appliance', 'appliance_name');
    }

    public function test_removing_an_appliance_keeps_it_with_a_removal_date(): void
    {
        $lease = $this->makeLease($this->makeRoom('201'), $this->owner);
        $this->actingAs($this->caretaker)->post(route('leases.appliances.store', $lease), $this->applianceForm(['date_added' => '2026-07-01']));
        $appliance = Appliance::firstOrFail();

        $this->actingAs($this->caretaker)->patch(route('appliances.remove', $appliance), ['date_removed' => '2026-06-30'])
            ->assertSessionHasErrorsIn('appliance', 'date_removed');

        $this->actingAs($this->caretaker)->patch(route('appliances.remove', $appliance), ['date_removed' => today()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame(today()->toDateString(), $appliance->refresh()->date_removed->toDateString());
        $this->assertSame(0, $lease->activeAppliances()->count());
        $this->assertSame(1, Appliance::count());
    }

    // ---------- Visitors ----------

    public function test_visitor_over_two_days_is_flagged(): void
    {
        $lease = $this->makeLease($this->makeRoom('201'), $this->owner);

        $this->actingAs($this->caretaker)->post(route('leases.visitors.store', $lease), [
            'visitor_name' => 'Sample Parent', 'relationship' => 'Parent', 'arrival_date' => today()->subDays(3)->toDateString(),
        ])->assertSessionHas('warning', 'Sample Parent stayed 3 days, more than the 2 days allowed by the MOA.');

        $this->actingAs($this->caretaker)->post(route('leases.visitors.store', $lease), [
            'visitor_name' => 'Sample Sibling', 'relationship' => 'Sibling',
            'arrival_date' => today()->subDays(5)->toDateString(), 'departure_date' => today()->subDays(3)->toDateString(),
        ])->assertSessionMissing('warning');

        $this->actingAs($this->caretaker)->get(route('leases.household', $lease))
            ->assertSee('Over 2 days (3)')
            ->assertSee('2 days');
    }

    public function test_recording_departure(): void
    {
        $lease = $this->makeLease($this->makeRoom('201'), $this->owner);
        $visitor = Visitor::create(['lease_id' => $lease->lease_id, 'visitor_name' => 'Sample Spouse', 'relationship' => 'Spouse',
            'arrival_date' => today()->subDay()->toDateString()]);

        $this->actingAs($this->caretaker)->patch(route('visitors.depart', $visitor), ['departure_date' => today()->subDays(2)->toDateString()])
            ->assertSessionHasErrorsIn('visitor', 'departure_date');

        $this->actingAs($this->caretaker)->patch(route('visitors.depart', $visitor), ['departure_date' => today()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame(today()->toDateString(), $visitor->refresh()->departure_date->toDateString());
    }

    public function test_visitor_validation(): void
    {
        $lease = $this->makeLease($this->makeRoom('201'), $this->owner);

        $this->actingAs($this->caretaker)->post(route('leases.visitors.store', $lease), [
            'visitor_name' => '', 'relationship' => 'Friend', 'arrival_date' => today()->addDay()->toDateString(),
        ])->assertSessionHasErrorsIn('visitor', ['visitor_name', 'relationship', 'arrival_date']);
    }

    // ---------- Inactive leases ----------

    public function test_nothing_can_be_added_to_an_ended_lease(): void
    {
        $lease = $this->makeLease($this->makeRoom('201'), $this->owner);
        $lease->update(['status' => 'Ended']);

        $this->actingAs($this->owner)->post(route('leases.appliances.store', $lease), $this->applianceForm())->assertForbidden();
        $this->actingAs($this->owner)->post(route('leases.visitors.store', $lease), [
            'visitor_name' => 'Sample', 'relationship' => 'Parent', 'arrival_date' => today()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($this->caretaker)->get(route('leases.household', $lease))->assertForbidden();
        $this->actingAs($this->owner)->get(route('leases.household', $lease))->assertOk()->assertDontSee('+ Log visitor');
    }
}
