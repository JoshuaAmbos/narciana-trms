<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Lease;
use App\Models\Occupant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

class LeasesTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->role('Owner')->create();
        $this->settings($owner);
        $this->manager = User::factory()->role('Manager')->create();
    }

    // ---------- Encoding a new MOA ----------

    public function test_encoding_an_moa_creates_tenant_lease_occupants_and_sets_room_occupied(): void
    {
        $room = $this->makeRoom('204');

        $response = $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, ['co_tenant_name' => 'Sample Co-tenant']));

        $lease = Lease::firstOrFail();
        $response->assertRedirect(route('leases.show', $lease))->assertSessionHas('status');

        $this->assertSame('Active', $lease->status);
        $this->assertSame('Sample Tenant A', $lease->tenant->full_name);
        $this->assertSame($this->manager->user_id, $lease->user_id);
        $this->assertSame('3500.00', $lease->monthly_rent);
        $this->assertSame('Occupied', $room->refresh()->status);

        $this->assertSame(['Sample Tenant A', 'Sample Co-tenant'], $lease->occupants()->orderByDesc('is_principal')->pluck('full_name')->all());
        $this->assertTrue($lease->principal->is_principal);
        $this->assertSame('2026-06-25', $lease->principal->date_added->toDateString());
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Lease created', 'table_name' => 'tbl_lease', 'record_id' => $lease->lease_id]);
    }

    public function test_due_day_defaults_to_the_move_in_day_and_advance_to_the_rent(): void
    {
        $room = $this->makeRoom('204');

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'move_in_date' => '2026-07-17', 'due_day' => '', 'advance_amount' => '',
        ]))->assertSessionHasNoErrors();

        $lease = Lease::firstOrFail();
        $this->assertSame(15, $lease->due_day);
        $this->assertSame('3500.00', $lease->advance_amount);
    }

    public function test_create_form_defaults(): void
    {
        $this->makeRoom('204');

        $todayDay = today()->day;
        $expectedDueDay = collect(Lease::STAGGERED_DUE_DAYS)->sortBy(fn ($d) => abs($d - $todayDay))->first();

        $this->actingAs($this->manager)->get('/leases/create')
            ->assertSee('Contractual Minimum Stay')
            ->assertSee('4 Months')
            ->assertSee('name="min_stay_months" id="min_stay_months" value="4"', false)
            ->assertSee('name="due_day"', false)
            ->assertSee('<option value="'.$expectedDueDay.'" selected', false);
    }

    public function test_opening_the_form_from_a_room_prefills_its_rent_and_advance(): void
    {
        $room = $this->makeRoom('204', 'Basic', ['monthly_rent' => 3250.50]);

        $this->actingAs($this->manager)->get(route('leases.create', ['room_id' => $room->room_id]))
            ->assertOk()
            ->assertSee('name="monthly_rent" type="number" min="0.01" step="0.01" inputmode="decimal" value="3250.50"', false)
            ->assertSee('name="advance_amount" type="number" min="0" step="0.01" inputmode="decimal" readonly', false)
            ->assertSee('value="3250.50"', false);
    }

    public function test_only_a_vacant_room_can_get_a_new_lease(): void
    {
        $room = $this->makeRoom('301', 'Basic', ['status' => 'Occupied']);

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room))
            ->assertSessionHasErrors(['room_id' => 'Choose a Vacant room. A new MOA can only be made for a Vacant room.']);

        $this->assertSame(0, Lease::count());
        $this->assertSame(0, Tenant::count());
    }

    public function test_the_same_room_cannot_be_leased_twice(): void
    {
        $room = $this->makeRoom('204');
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room))->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, ['full_name' => 'Sample Tenant B']))
            ->assertSessionHasErrors('room_id');

        $this->assertSame(1, Lease::count());
    }

    public function test_co_tenant_not_allowed_in_a_one_person_room(): void
    {
        $room = $this->makeRoom('105', 'Basic', ['max_occupants' => 1]);

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, ['co_tenant_name' => 'Sample Co-tenant']))
            ->assertSessionHasErrors(['co_tenant_name' => 'Room 105 allows only 1 occupant, so there can be no co-tenant.']);

        $this->assertSame(0, Lease::count());
        $this->assertSame('Vacant', $room->refresh()->status, 'Nothing is saved when the lease is refused');
    }

    public function test_returning_tenant_can_get_a_new_lease_but_not_two_active_ones(): void
    {
        $first = $this->makeRoom('201');
        $second = $this->makeRoom('202');
        $lease = $this->makeLease($first, $this->manager);

        // Still active: cannot be picked again.
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($second, ['tenant_mode' => 'existing', 'tenant_id' => $lease->tenant_id]))
            ->assertSessionHasErrors(['tenant_id' => 'This tenant already has an active lease.']);

        $lease->update(['status' => 'Ended']);
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($second, ['tenant_mode' => 'existing', 'tenant_id' => $lease->tenant_id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Tenant::count());
        $this->assertSame(2, Lease::where('tenant_id', $lease->tenant_id)->count());
    }

    public function test_moa_validation(): void
    {
        $room = $this->makeRoom('204');

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'full_name' => '', 'due_day' => '32', 'monthly_rent' => '0', 'min_stay_months' => '0',
            'deposit_damage' => '-1', 'deposit_keys' => '300.555', 'moa_date' => 'yesterday',
        ]))->assertSessionHasErrors(['full_name', 'due_day', 'monthly_rent', 'min_stay_months', 'deposit_damage', 'deposit_keys', 'moa_date']);

        $this->assertSame(0, Lease::count());
    }

    // ---------- Deposit warning (not a block) ----------

    public function test_deposit_not_equal_to_one_month_fails_validation(): void
    {
        $room = $this->makeRoom('204');

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, ['deposit_keys' => '200.00']))
            ->assertSessionHasErrors(['deposit_maintenance']);

        $this->assertSame(0, Lease::count());
    }

    public function test_deposit_equal_to_one_month_has_no_warning(): void
    {
        $room = $this->makeRoom('204');

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room))->assertSessionMissing('warning');
    }

    // ---------- Viewing and editing ----------

    public function test_moa_page_shows_details_and_deposit_breakdown(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);

        $this->actingAs($this->manager)->get(route('leases.show', $lease))
            ->assertOk()
            ->assertSee('Every 25th')
            ->assertSee('Maintenance (non-refundable)')
            ->assertSee('₱3,500.00')
            ->assertSee('N')->assertSee('P1234567');
    }

    public function test_editing_the_moa_is_audited_and_cannot_change_room_or_tenant(): void
    {
        $room = $this->makeRoom('204');
        $other = $this->makeRoom('205');
        $lease = $this->makeLease($room, $this->manager);

        $this->actingAs($this->manager)->put(route('leases.update', $lease), array_merge($this->moaForm($room), [
            'monthly_rent' => '3600.00', 'room_id' => $other->room_id, 'tenant_id' => 999,
        ]))->assertRedirect(route('leases.show', $lease));

        $lease->refresh();
        $this->assertSame('3600.00', $lease->monthly_rent);
        $this->assertSame($room->room_id, $lease->room_id);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Lease changed', 'record_id' => $lease->lease_id]);
    }

    public function test_correcting_the_tenant_name_updates_the_principal_occupant(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);

        $this->actingAs($this->manager)->put(route('tenants.update', $lease->tenant), [
            'full_name' => 'Sample Tenant Corrected', 'contact_no' => '0918 555 0123', 'valid_id_type' => 'Passport', 'valid_id_no' => 'P1234567',
            'provincial_address' => 'Purok 2, Sample Barangay, Bukidnon', 'occupation' => 'Nurse',
        ])->assertRedirect(route('leases.show', $lease));

        $this->assertSame('Sample Tenant Corrected', $lease->principal()->value('full_name'));
    }

    // ---------- Renter's Profile fields ----------

    public function test_moa_saves_renter_profile_fields_and_previous_building_is_optional(): void
    {
        $room = $this->makeRoom('204');

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room))->assertSessionHasNoErrors();

        $tenant = Tenant::firstOrFail();
        $this->assertSame('Purok 1, Sample Barangay, Davao del Norte', $tenant->provincial_address);
        $this->assertSame('Student', $tenant->occupation);
    }

    public function test_moa_requires_provincial_address_and_occupation_for_a_new_tenant(): void
    {
        $room = $this->makeRoom('204');

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($room, [
            'provincial_address' => '', 'occupation' => '',
        ]))->assertSessionHasErrors(['provincial_address', 'occupation']);

        $this->assertSame(0, Tenant::count());
    }

    public function test_returning_tenant_needs_no_profile_fields(): void
    {
        $old = $this->makeLease($this->makeRoom('204'), $this->manager);
        $old->update(['status' => 'Ended']);

        $this->actingAs($this->manager)->post('/leases', $this->moaForm($this->makeRoom('205'), [
            'tenant_mode' => 'existing', 'tenant_id' => $old->tenant_id,
            'full_name' => '', 'contact_no' => '', 'valid_id_type' => '', 'valid_id_no' => '', 'provincial_address' => '', 'occupation' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Tenant::count());
    }

    public function test_tenant_edit_requires_and_updates_renter_profile_fields(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $form = ['full_name' => 'Sample Tenant A', 'contact_no' => '0918 555 0123', 'valid_id_type' => 'Passport', 'valid_id_no' => 'P1234567'];

        $this->actingAs($this->manager)->put(route('tenants.update', $lease->tenant), $form + ['provincial_address' => '', 'occupation' => ''])
            ->assertSessionHasErrors(['provincial_address', 'occupation']);

        $this->actingAs($this->manager)->put(route('tenants.update', $lease->tenant), $form + [
            'provincial_address' => 'Purok 9, Sample Barangay, Cotabato', 'occupation' => 'Teacher',
        ])->assertSessionHasNoErrors();

        $tenant = $lease->tenant->refresh();
        $this->assertSame('Purok 9, Sample Barangay, Cotabato', $tenant->provincial_address);
        $this->assertSame('Teacher', $tenant->occupation);
    }

    public function test_forms_and_moa_page_show_renter_profile_fields(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->makeRoom('205');
        $m = $this->actingAs($this->manager);

        $m->get(route('leases.create'))->assertOk()
            ->assertSee('name="provincial_address"', false)->assertSee('name="occupation"', false);

        $m->get(route('tenants.edit', $lease->tenant))->assertOk()
            ->assertSee('value="Purok 2, Sample Barangay, Bukidnon"', false)->assertSee('value="Nurse"', false);

        $m->get(route('leases.show', $lease))->assertOk()
            ->assertSee('Purok 2, Sample Barangay, Bukidnon')->assertSee('Nurse');
    }

    public function test_tenant_encoded_before_the_profile_fields_still_opens(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $lease->tenant->update(['provincial_address' => null, 'occupation' => null]);

        $this->actingAs($this->manager)->get(route('leases.show', $lease))->assertOk()->assertSee('Not recorded');
        $this->actingAs($this->manager)->get(route('tenants.edit', $lease->tenant))->assertOk();
    }

    // ---------- Cancelling ----------

    public function test_lease_without_bills_can_be_cancelled_and_room_becomes_vacant(): void
    {
        $room = $this->makeRoom('204');
        $lease = $this->makeLease($room, $this->manager);

        $this->actingAs($this->manager)->patch(route('leases.cancel', $lease))->assertRedirect(route('leases.show', $lease));

        $this->assertSame('Cancelled', $lease->refresh()->status);
        $this->assertSame('Vacant', $room->refresh()->status);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Lease cancelled', 'record_id' => $lease->lease_id]);
    }

    public function test_lease_with_a_bill_cannot_be_cancelled(): void
    {
        $room = $this->makeRoom('204');
        $lease = $this->makeLease($room, $this->manager);
        Bill::create([
            'lease_id' => $lease->lease_id, 'period_start' => '2026-06-25', 'due_date' => '2026-07-25',
            'rent_amount' => 3500, 'total_amount' => 3500, 'status' => 'Unpaid', 'user_id' => $this->manager->user_id,
        ]);

        $this->actingAs($this->manager)->get(route('leases.show', $lease))->assertDontSee('Cancel this MOA');
        $this->actingAs($this->manager)->patch(route('leases.cancel', $lease))->assertForbidden();

        $this->assertSame('Active', $lease->refresh()->status);
        $this->assertSame('Occupied', $room->refresh()->status);
    }

    public function test_cancelled_lease_cannot_be_cancelled_again(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $lease->update(['status' => 'Cancelled']);

        $this->actingAs($this->manager)->patch(route('leases.cancel', $lease))->assertForbidden();
    }

    // ---------- Occupants ----------

    public function test_third_occupant_is_blocked(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager, 'Sample Co-tenant');

        $this->actingAs($this->manager)->post(route('leases.occupants.store', $lease), ['full_name' => 'Sample Third', 'date_added' => today()->toDateString()])
            ->assertSessionHasErrorsIn('occupant', ['full_name' => 'Room 204 allows at most 2 occupants and 2 already staying.']);

        $this->assertSame(2, $lease->activeOccupants()->count());
    }

    public function test_second_occupant_blocked_in_a_one_person_room(): void
    {
        $lease = $this->makeLease($this->makeRoom('105', 'Basic', ['max_occupants' => 1]), $this->manager);

        $this->actingAs($this->manager)->post(route('leases.occupants.store', $lease), ['full_name' => 'Sample Second', 'date_added' => today()->toDateString()])
            ->assertSessionHasErrorsIn('occupant', ['full_name' => 'Room 105 allows at most 1 occupant and 1 already staying.']);
    }

    public function test_removed_occupant_frees_the_slot_and_keeps_history(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager, 'Sample Co-tenant');
        $co = $lease->occupants()->where('is_principal', false)->first();

        $this->actingAs($this->manager)->patch(route('occupants.remove', $co), ['date_removed' => today()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($co->refresh()->date_removed);

        $this->actingAs($this->manager)->post(route('leases.occupants.store', $lease), ['full_name' => 'Sample New Co-tenant', 'date_added' => today()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, Occupant::where('lease_id', $lease->lease_id)->count(), 'Removed occupant stays in history');
        $this->assertSame(2, $lease->activeOccupants()->count());
    }

    public function test_principal_renter_cannot_be_removed(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);

        $this->actingAs($this->manager)->patch(route('occupants.remove', $lease->principal), ['date_removed' => today()->toDateString()])
            ->assertSessionHasErrorsIn('occupant', 'date_removed');

        $this->assertNull($lease->principal->refresh()->date_removed);
    }
}
