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
}
