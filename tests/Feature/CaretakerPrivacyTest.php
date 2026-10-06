<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/**
 * Decision 10: the Caretaker opens Tenants & MOA only for appliances and visitors and must never
 * view or edit MOA, deposit, rent, tenant ID/contact/profile or co-tenant details.
 */
class CaretakerPrivacyTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $caretaker;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::factory()->role('Owner')->create();
        $this->settings($owner);
        $this->caretaker = User::factory()->role('Caretaker')->create();

        // Distinctive MOA values so any leak is easy to spot. The room's list rent (₱3,500.00, shown on the
        // Rooms page to every role, as approved in Module 3) is deliberately different from the MOA rent.
        $room = $this->makeRoom('207', 'Basic', ['monthly_rent' => 3500]);
        $this->lease = $this->makeLease($room, $owner, 'Sample Secret Co-tenant', 'Sample Visible Tenant');
        $this->lease->update(['monthly_rent' => 4321.99, 'advance_amount' => 4321.99, 'deposit_maintenance' => 1111.11,
            'deposit_damage' => 2222.22, 'deposit_bills' => 777.77, 'deposit_keys' => 210.89]);
        $this->lease->tenant->update(['contact_no' => '0999 888 7777', 'valid_id_no' => 'SECRET-ID-42',
            'provincial_address' => 'Secret Provincial Address', 'occupation' => 'Secret Occupation']);
    }

    public function test_caretaker_gets_403_on_every_moa_route(): void
    {
        $lease = $this->lease;
        $occupant = $lease->principal;
        $c = $this->actingAs($this->caretaker);

        $c->get(route('leases.create'))->assertForbidden();
        $c->post(route('leases.store'), [])->assertForbidden();
        $c->get(route('leases.show', $lease))->assertForbidden();
        $c->get(route('leases.edit', $lease))->assertForbidden();
        $c->put(route('leases.update', $lease), [])->assertForbidden();
        $c->patch(route('leases.cancel', $lease))->assertForbidden();
        $c->get(route('tenants.edit', $lease->tenant))->assertForbidden();
        $c->put(route('tenants.update', $lease->tenant), [])->assertForbidden();
        $c->post(route('leases.occupants.store', $lease), ['full_name' => 'X', 'date_added' => today()->toDateString()])->assertForbidden();
        $c->patch(route('occupants.remove', $occupant), ['date_removed' => today()->toDateString()])->assertForbidden();

        $this->assertSame('Active', $lease->refresh()->status);
    }

    public function test_no_moa_rent_deposit_id_contact_or_co_tenant_value_reaches_the_caretaker(): void
    {
        $secrets = [
            '4,321.99', '4321.99', '1,111.11', '2,222.22', '777.77', '210.89', // rent, advance, deposits
            '0999 888 7777', 'SECRET-ID-42', 'Sample Secret Co-tenant',          // contact, ID, co-tenant
            'Secret Provincial Address', 'Secret Occupation', 'Secret Building', // Renter's Profile
            'MOA signed', 'Maintenance (non-refundable)', 'Every 25th', '24 Jun 2026', '25 Jun 2026',
        ];

        $pages = [
            route('tenants.index'),
            route('leases.household', $this->lease),
            route('dashboard'),
        ];

        foreach ($pages as $url) {
            $response = $this->actingAs($this->caretaker)->get($url)->assertOk();
            foreach ($secrets as $secret) {
                $response->assertDontSee($secret, false);
            }
        }
    }

    public function test_caretaker_sees_only_room_number_and_tenant_name_in_the_list(): void
    {
        $this->actingAs($this->caretaker)->get(route('tenants.index'))
            ->assertOk()
            ->assertSee('207')
            ->assertSee('Sample Visible Tenant')
            ->assertSee('Appliances &amp; visitors', false)
            ->assertDontSee('+ Encode new MOA')
            ->assertDontSee('href="'.route('leases.show', $this->lease).'"', false); // the household link /leases/1/household is allowed
    }

    public function test_caretaker_does_not_see_the_encode_moa_button_on_rooms(): void
    {
        $this->makeRoom('301');

        $this->actingAs($this->caretaker)->get(route('rooms.index'))->assertOk()->assertDontSee('Encode MOA');
    }
}
