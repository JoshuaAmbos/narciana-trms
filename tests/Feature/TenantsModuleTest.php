<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

class TenantsModuleTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $manager;

    private User $owner;

    private User $caretaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
        $this->manager = User::factory()->role('Manager')->create();
        $this->caretaker = User::factory()->role('Caretaker')->create();
    }

    public function test_tenants_directory_loads_with_metrics_and_searchable_records(): void
    {
        $room = $this->makeRoom('201');
        $lease = $this->makeLease($room, $this->manager, null, 'Alpha Tenant');

        $inactiveTenant = Tenant::create([
            'full_name' => 'Beta Inactive Tenant',
            'contact_no' => '0917 111 2222',
            'valid_id_type' => 'PhilSys National ID',
            'valid_id_no' => '1234-5678-9012',
            'provincial_address' => 'Davao City',
            'occupation' => 'Freelancer',
            'status' => 'Inactive',
        ]);

        $response = $this->actingAs($this->manager)->get(route('tenants.index'));

        $response->assertOk()
            ->assertSee('Tenants Directory')
            ->assertSee('Registered Tenants')
            ->assertSee('Alpha Tenant')
            ->assertSee('Room 201')
            ->assertSee('Beta Inactive Tenant')
            ->assertSee('Inactive');
    }

    public function test_tenants_directory_search_and_filters(): void
    {
        $t1 = Tenant::create([
            'full_name' => 'John Doe',
            'contact_no' => '0918 000 1111',
            'valid_id_type' => 'Passport',
            'valid_id_no' => 'P9999999',
            'provincial_address' => 'Tagum City',
            'occupation' => 'Engineer',
            'status' => 'Active',
        ]);

        $t2 = Tenant::create([
            'full_name' => 'Jane Smith',
            'contact_no' => '0919 222 3333',
            'valid_id_type' => 'Driver\'s License',
            'valid_id_no' => 'D8888888',
            'provincial_address' => 'Digos City',
            'occupation' => 'Teacher',
            'status' => 'Blacklisted',
        ]);

        $this->actingAs($this->manager)->get(route('tenants.index', ['search' => 'Tagum']))
            ->assertOk()
            ->assertSee('John Doe')
            ->assertDontSee('Jane Smith');

        $this->actingAs($this->manager)->get(route('tenants.index', ['status' => 'Blacklisted']))
            ->assertOk()
            ->assertSee('Jane Smith')
            ->assertDontSee('John Doe');
    }

    public function test_registering_a_new_tenant_master_profile(): void
    {
        $payload = [
            'full_name' => 'New Renter Profile',
            'contact_no' => '0917 999 8888',
            'valid_id_type' => 'UMID',
            'valid_id_no' => 'CRN-12345678',
            'provincial_address' => 'Panabo City, Davao del Norte',
            'occupation' => 'Accountant',
            'prev_building' => 'Sunshine Dormitory',
            'status' => 'Active',
        ];

        $res = $this->actingAs($this->manager)->post(route('tenants.store'), $payload);

        $tenant = Tenant::where('full_name', 'New Renter Profile')->firstOrFail();
        $res->assertRedirect(route('tenants.index'))
            ->assertSessionHas('prompt_lease', true)
            ->assertSessionHas('new_tenant_id', $tenant->tenant_id);

        $this->assertSame('Panabo City, Davao del Norte', $tenant->provincial_address);
        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Tenant registered',
            'table_name' => 'tbl_tenant',
            'record_id' => $tenant->tenant_id,
        ]);
    }

    public function test_tenant_show_page_displays_profile_credentials_and_tenancy_history(): void
    {
        $room1 = $this->makeRoom('201');
        $room2 = $this->makeRoom('202');
        $lease1 = $this->makeLease($room1, $this->manager, null, 'Repeat Renter');
        $lease1->update(['status' => 'Ended']);

        $lease2 = Lease::create([
            'tenant_id' => $lease1->tenant_id,
            'room_id' => $room2->room_id,
            'moa_date' => '2026-08-01',
            'move_in_date' => '2026-08-01',
            'due_day' => 15,
            'monthly_rent' => 3600.00,
            'min_stay_months' => 4,
            'advance_amount' => 3600.00,
            'deposit_maintenance' => 1500.00,
            'deposit_damage' => 1500.00,
            'deposit_bills' => 500.00,
            'deposit_keys' => 100.00,
            'status' => 'Active',
            'user_id' => $this->manager->user_id,
        ]);

        $res = $this->actingAs($this->manager)->get(route('tenants.show', $lease1->tenant));

        $res->assertOk()
            ->assertSee('Repeat Renter')
            ->assertSee('Room 202')
            ->assertSee('Tenancy History (MOA Agreements)')
            ->assertSee('MOA-'.str_pad($lease1->lease_id, 4, '0', STR_PAD_LEFT))
            ->assertSee('MOA-'.str_pad($lease2->lease_id, 4, '0', STR_PAD_LEFT));
    }

    public function test_leases_index_tabbed_view(): void
    {
        $room1 = $this->makeRoom('201');
        $room2 = $this->makeRoom('202');
        $activeLease = $this->makeLease($room1, $this->manager, null, 'Active Renter Person');
        $endedLease = $this->makeLease($room2, $this->manager, null, 'Ended Renter Person');
        $endedLease->update(['status' => 'Ended']);

        $this->actingAs($this->manager)->get(route('leases.index', ['status' => 'Active']))
            ->assertOk()
            ->assertSee('Active Renter Person')
            ->assertDontSee('Ended Renter Person');

        $this->actingAs($this->manager)->get(route('leases.index', ['status' => 'Ended']))
            ->assertOk()
            ->assertSee('Ended Renter Person')
            ->assertDontSee('Active Renter Person');
    }

    public function test_lease_create_preselects_tenant_via_query_parameter(): void
    {
        $tenant = Tenant::create([
            'full_name' => 'Preselected Applicant',
            'contact_no' => '0917 444 5555',
            'valid_id_type' => 'PhilSys National ID',
            'valid_id_no' => '5555-6666-7777',
            'provincial_address' => 'Mati City',
            'occupation' => 'Student',
            'status' => 'Active',
        ]);
        $this->makeRoom('205');

        $res = $this->actingAs($this->manager)->get(route('leases.create', ['tenant_id' => $tenant->tenant_id]));

        $res->assertOk()
            ->assertSee('Preselected Applicant')
            ->assertSee('value="'.$tenant->tenant_id.'" selected', false);
    }

    public function test_caretaker_cannot_view_or_create_tenant_profiles(): void
    {
        $tenant = Tenant::create([
            'full_name' => 'Secret Tenant',
            'contact_no' => '0999 000 0000',
            'valid_id_type' => 'Passport',
            'valid_id_no' => 'SECRET',
            'provincial_address' => 'Secret',
            'occupation' => 'Secret',
        ]);

        $c = $this->actingAs($this->caretaker);
        $c->get(route('tenants.create'))->assertForbidden();
        $c->post(route('tenants.store'), [])->assertForbidden();
        $c->get(route('tenants.show', $tenant))->assertForbidden();
        $c->get(route('tenants.edit', $tenant))->assertForbidden();
        $c->get(route('leases.index'))->assertForbidden();
    }

    public function test_register_tenant_with_split_name_fields_and_philippine_phone_format(): void
    {
        $payload = [
            'first_name' => 'Juan',
            'middle_name' => 'Protacio',
            'last_name' => 'Rizal',
            'name_extension' => 'Jr.',
            'contact_no' => '+639171234567',
            'valid_id_type' => 'PhilSys National ID (PCN)',
            'valid_id_no' => '1234-5678-9012-3456',
            'provincial_address' => 'Calamba, Laguna',
            'occupation' => 'Ophthalmologist',
            'status' => 'Active',
        ];

        $res = $this->actingAs($this->manager)->post(route('tenants.store'), $payload);

        $res->assertRedirect(route('tenants.index'))
            ->assertSessionHas('prompt_lease', true);

        $tenant = Tenant::where('last_name', 'Rizal')->firstOrFail();
        $this->assertSame('Juan Protacio Rizal Jr.', $tenant->full_name);
        $this->assertSame('+639171234567', $tenant->contact_no);
        $this->assertSame('1234-5678-9012-3456', $tenant->valid_id_no);
        $this->assertSame('12*************3456', $tenant->masked_id_number);
    }

    public function test_phone_validation_rejects_non_philippine_formats(): void
    {
        $payload = [
            'first_name' => 'Carlos',
            'last_name' => 'Garcia',
            'contact_no' => '08123456789', // Invalid prefix (not 09 or +639)
            'valid_id_type' => 'Driver\'s License',
            'valid_id_no' => 'N01-99-123456',
            'provincial_address' => 'Talibon, Bohol',
            'occupation' => 'Teacher',
        ];

        $res = $this->actingAs($this->manager)->post(route('tenants.store'), $payload);
        $res->assertSessionHasErrors(['contact_no']);
    }

    public function test_other_id_type_is_required_when_valid_id_type_is_other(): void
    {
        $payload = [
            'first_name' => 'Apolinario',
            'last_name' => 'Mabini',
            'contact_no' => '09181234567',
            'valid_id_type' => 'Other',
            'other_id_type' => '', // Missing required specification
            'valid_id_no' => 'PWD-2026-001',
            'provincial_address' => 'Tanauan, Batangas',
            'occupation' => 'Lawyer',
        ];

        $res = $this->actingAs($this->manager)->post(route('tenants.store'), $payload);
        $res->assertSessionHasErrors(['other_id_type']);

        $payload['other_id_type'] = 'PWD Identification Card';
        $res2 = $this->actingAs($this->manager)->post(route('tenants.store'), $payload);
        $res2->assertSessionHasNoErrors();
    }

    public function test_masked_id_number_displayed_in_directory_table(): void
    {
        $tenant = Tenant::create([
            'first_name' => 'Maria',
            'last_name' => 'Clara',
            'contact_no' => '09171239999',
            'valid_id_type' => 'UMID',
            'valid_id_no' => 'CRN-987654321',
            'provincial_address' => 'San Diego',
            'occupation' => 'Musician',
            'status' => 'Active',
        ]);

        $res = $this->actingAs($this->manager)->get(route('tenants.index'));

        $res->assertOk()
            ->assertSee($tenant->masked_id_number)
            ->assertDontSee('CRN-987654321');
    }

    public function test_tenant_creation_form_omits_status_input_and_defaults_status_to_active(): void
    {
        // Form intake doesn't have a status selector
        $this->actingAs($this->manager)->get(route('leases.create'))
            ->assertOk()
            ->assertDontSee('<select id="status" name="status"', false);

        $payload = [
            'first_name' => 'Juan',
            'last_name' => 'Luna',
            'contact_no' => '09171234567',
            'valid_id_type' => 'Driver\'s License',
            'valid_id_no' => 'D01-12-345678',
            'provincial_address' => 'Badoc, Ilocos Norte',
            'occupation' => 'Painter',
        ];

        $res = $this->actingAs($this->manager)->post(route('tenants.store'), $payload);
        $res->assertRedirect(route('tenants.index'));

        $tenant = Tenant::where('first_name', 'Juan')->firstOrFail();
        $this->assertSame('Active', $tenant->status);
    }

    public function test_leases_index_paginates_and_retains_query_strings(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $room = $this->makeRoom((string) (300 + $i));
            $this->makeLease($room, $this->manager, null, "Tenant {$i}");
        }

        $resPage1 = $this->actingAs($this->manager)->get(route('leases.index', ['status' => 'Active', 'page' => 1]));
        $resPage1->assertOk()
            ->assertSee('Page 1 of 2')
            ->assertSee('pager-btn')
            ->assertSee('Next');

        $resPage2 = $this->actingAs($this->manager)->get(route('leases.index', ['status' => 'Active', 'page' => 2]));
        $resPage2->assertOk()
            ->assertSee('Page 2 of 2')
            ->assertSee('Previous');
    }

    public function test_household_show_back_to_leases_button_navigates_to_leases_index(): void
    {
        $room = $this->makeRoom('201');
        $lease = $this->makeLease($room, $this->manager, null, 'Household Test Tenant');

        $res = $this->actingAs($this->manager)->get(route('leases.household', $lease));
        $res->assertOk()
            ->assertSee('Back to Leases')
            ->assertSee('href="'.route('leases.index').'"', false);
    }

    public function test_differential_minimum_stay_enforcement_for_basic_and_aircon(): void
    {
        $basicRoom = $this->makeRoom('201', 'Basic');
        $airconRoom = $this->makeRoom('202', 'Aircon');

        // Check badge text when room is preselected
        $resBasic = $this->actingAs($this->manager)->get(route('leases.create', ['room_id' => $basicRoom->room_id]));
        $resBasic->assertOk()
            ->assertSee('Minimum Stay: 4 Months (MOA Basic Terms — premature departure forfeits deposit)');

        $resAircon = $this->actingAs($this->manager)->get(route('leases.create', ['room_id' => $airconRoom->room_id]));
        $resAircon->assertOk()
            ->assertSee('Minimum Stay: 6 Months (MOA Aircon Terms — premature departure forfeits deposit)');

        // Basic room with 3 months fails validation
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($basicRoom, [
            'min_stay_months' => 3,
        ]))->assertSessionHasErrors('min_stay_months');

        // Aircon room with 4 months fails validation
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($airconRoom, [
            'min_stay_months' => 4,
            'move_in_reading' => '1000.00',
        ]))->assertSessionHasErrors('min_stay_months');

        // Aircon room with 6 months passes
        $this->actingAs($this->manager)->post('/leases', $this->moaForm($airconRoom, [
            'min_stay_months' => 6,
            'move_in_reading' => '1000.00',
        ]))->assertSessionHasNoErrors();

        $airconLease = Lease::where('room_id', $airconRoom->room_id)->firstOrFail();
        $this->assertSame(6, (int) $airconLease->min_stay_months);
    }
}
