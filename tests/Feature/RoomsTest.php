<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
    }

    private function room(array $attributes = []): Room
    {
        return Room::create($attributes + [
            'room_no' => '201', 'room_type' => 'Basic', 'is_metered' => false, 'max_occupants' => 2,
            'monthly_rent' => 3000, 'status' => 'Vacant',
        ]);
    }

    private function validRoom(array $overrides = []): array
    {
        return array_merge([
            'room_no' => '212', 'room_type' => 'Aircon', 'is_metered' => '1', 'max_occupants' => '2', 'monthly_rent' => '3600.00',
        ], $overrides);
    }

    // ---------- Viewing ----------

    public function test_every_role_sees_room_availability(): void
    {
        $this->room();

        foreach (User::ROLES as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get('/rooms')
                ->assertOk()->assertSee('Room availability')->assertSee('201')->assertDontSee('Log an inquiry');
        }
    }

    public function test_only_the_owner_sees_add_and_edit_buttons(): void
    {
        $room = $this->room();

        $this->actingAs($this->owner)->get('/rooms')
            ->assertSee('Add room')->assertSee(route('rooms.edit', $room), false);

        foreach (['Manager', 'Caretaker'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get('/rooms')
                ->assertDontSee('Add room')->assertDontSee(route('rooms.edit', $room), false);
        }
    }

    public function test_room_statuses_are_strictly_vacant_or_occupied(): void
    {
        $this->assertSame(['Vacant', 'Occupied'], Room::STATUSES);

        $this->actingAs($this->owner)->get('/rooms')
            ->assertOk()
            ->assertDontSee('data-status="Vacating"', false)
            ->assertDontSee('status=Vacating', false);

        $this->actingAs($this->owner)->get('/')
            ->assertOk()
            ->assertDontSee('data-status="Vacating"', false);
    }

    // ---------- Add / edit (Owner) ----------

    public function test_owner_can_add_a_room_which_starts_vacant(): void
    {
        $this->actingAs($this->owner)->post('/rooms', $this->validRoom(['status' => 'Occupied']))
            ->assertRedirect('/rooms')->assertSessionHas('status', 'Room 212 was added.');

        $room = Room::where('room_no', '212')->firstOrFail();
        $this->assertSame('Aircon', $room->room_type);
        $this->assertTrue($room->is_metered);
        $this->assertSame('3600.00', $room->monthly_rent);
        $this->assertSame('Vacant', $room->status, 'Status cannot be set from the form');
        $this->assertDatabaseHas('tbl_audit_log', [
            'user_id' => $this->owner->user_id, 'action' => 'Room created', 'table_name' => 'tbl_room', 'record_id' => $room->room_id,
        ]);
    }

    public function test_owner_can_set_a_basic_room_as_metered_and_an_aircon_room_as_not(): void
    {
        $this->actingAs($this->owner)->post('/rooms', $this->validRoom(['room_no' => '111', 'room_type' => 'Basic', 'is_metered' => '1']));
        $this->actingAs($this->owner)->post('/rooms', $this->validRoom(['room_no' => '112', 'room_type' => 'Aircon', 'is_metered' => null]));

        $this->assertTrue(Room::where('room_no', '111')->value('is_metered'));
        $this->assertFalse((bool) Room::where('room_no', '112')->value('is_metered'));
    }

    public function test_owner_can_edit_a_room_but_not_its_status(): void
    {
        $room = $this->room(['status' => 'Occupied']);

        $this->actingAs($this->owner)->put("/rooms/{$room->room_id}", $this->validRoom([
            'room_no' => '201', 'room_type' => 'Basic', 'is_metered' => null, 'monthly_rent' => '3250.50', 'status' => 'Vacant',
        ]))->assertRedirect('/rooms');

        $room->refresh();
        $this->assertSame('3250.50', $room->monthly_rent);
        $this->assertSame('Occupied', $room->status);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Room changed', 'record_id' => $room->room_id]);
    }

    public function test_manager_and_caretaker_cannot_add_or_edit_rooms(): void
    {
        $room = $this->room();

        foreach (['Manager', 'Caretaker'] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get('/rooms/create')->assertForbidden();
            $this->actingAs($user)->post('/rooms', $this->validRoom())->assertForbidden();
            $this->actingAs($user)->get("/rooms/{$room->room_id}/edit")->assertForbidden();
            $this->actingAs($user)->put("/rooms/{$room->room_id}", $this->validRoom(['room_no' => '201']))->assertForbidden();
        }

        $this->assertSame(1, Room::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_room_validation(): void
    {
        $this->room();

        $this->actingAs($this->owner)->post('/rooms', $this->validRoom([
            'room_no' => '201',          // already exists
            'room_type' => 'Suite',      // not a type
            'max_occupants' => '3',      // max is 2
            'monthly_rent' => '0',       // must be more than 0
        ]))->assertSessionHasErrors(['room_no', 'room_type', 'max_occupants', 'monthly_rent']);

        $this->actingAs($this->owner)->post('/rooms', $this->validRoom(['monthly_rent' => '3000.555']))
            ->assertSessionHasErrors(['monthly_rent']);

        // Rejects non-number room numbers
        $this->actingAs($this->owner)->post('/rooms', $this->validRoom(['room_no' => 'ABC']))
            ->assertSessionHasErrors(['room_no']);
        $this->actingAs($this->owner)->post('/rooms', $this->validRoom(['room_no' => 'Room-1']))
            ->assertSessionHasErrors(['room_no']);

        // Rejects non-money monthly rent
        $this->actingAs($this->owner)->post('/rooms', $this->validRoom(['monthly_rent' => 'not-money']))
            ->assertSessionHasErrors(['monthly_rent']);

        $this->assertSame(1, Room::count());
    }

    public function test_editing_a_room_may_keep_its_own_number(): void
    {
        $room = $this->room();

        $this->actingAs($this->owner)->put("/rooms/{$room->room_id}", $this->validRoom(['room_no' => '201']))
            ->assertSessionHasNoErrors();
    }

    public function test_encode_moa_button_strictly_rendered_for_vacant_rooms_only_to_owner_and_manager(): void
    {
        $vacant = $this->room(['room_no' => '101', 'status' => 'Vacant']);
        $occupied = $this->room(['room_no' => '102', 'status' => 'Occupied']);

        $manager = User::factory()->role('Manager')->create();
        $caretaker = User::factory()->role('Caretaker')->create();

        // Owner and Manager see Encode MOA ONLY for the vacant room
        foreach ([$this->owner, $manager] as $user) {
            $response = $this->actingAs($user)->get('/rooms')->assertOk();
            $response->assertSee(route('leases.create', ['room_id' => $vacant->room_id]), false);
            $response->assertDontSee(route('leases.create', ['room_id' => $occupied->room_id]), false);
        }

        // Caretaker does not see Encode MOA for any room
        $this->actingAs($caretaker)->get('/rooms')->assertOk()->assertDontSee('Encode MOA');
    }

    public function test_waiting_inquiry_demand_calculated_and_displayed(): void
    {
        Inquiry::create([
            'prospect_name' => 'Alice',
            'contact_no' => '0917 111 2233',
            'inquiry_date' => today()->toDateString(),
            'preferred_type' => 'Aircon',
            'no_of_occupants' => 2,
            'status' => 'Waiting',
            'user_id' => $this->owner->user_id,
        ]);

        Inquiry::create([
            'prospect_name' => 'Bob',
            'contact_no' => '0917 222 3344',
            'inquiry_date' => today()->toDateString(),
            'preferred_type' => 'Aircon',
            'no_of_occupants' => 1,
            'status' => 'Waiting',
            'user_id' => $this->owner->user_id,
        ]);

        Inquiry::create([
            'prospect_name' => 'Charlie',
            'contact_no' => '0917 333 4455',
            'inquiry_date' => today()->toDateString(),
            'preferred_type' => 'Any',
            'no_of_occupants' => 2,
            'status' => 'Waiting',
            'user_id' => $this->owner->user_id,
        ]);

        // Declined inquiry must not be counted
        Inquiry::create([
            'prospect_name' => 'Declined Dave',
            'contact_no' => '0917 444 5566',
            'inquiry_date' => today()->toDateString(),
            'preferred_type' => 'Aircon',
            'no_of_occupants' => 2,
            'status' => 'Declined',
            'user_id' => $this->owner->user_id,
        ]);

        $airconRoom = $this->room(['room_no' => '212', 'room_type' => 'Aircon']);
        $basicRoom = $this->room(['room_no' => '201', 'room_type' => 'Basic']);

        $response = $this->actingAs($this->owner)->get('/rooms')->assertOk();

        // Aircon has 2 Aircon + 1 Any = 3 waiting
        // Basic has 0 Basic + 1 Any = 1 waiting
        $response->assertSee('3 waiting');
        $response->assertSee('1 waiting');
    }

    public function test_room_policy_encode_moa_rules(): void
    {
        $vacant = $this->room(['room_no' => '201', 'status' => 'Vacant']);
        $occupied = $this->room(['room_no' => '202', 'status' => 'Occupied']);

        $manager = User::factory()->role('Manager')->create();
        $caretaker = User::factory()->role('Caretaker')->create();

        $this->assertTrue($this->owner->can('encodeMoa', $vacant));
        $this->assertTrue($manager->can('encodeMoa', $vacant));
        $this->assertFalse($caretaker->can('encodeMoa', $vacant));

        $this->assertFalse($this->owner->can('encodeMoa', $occupied));
        $this->assertFalse($manager->can('encodeMoa', $occupied));
        $this->assertFalse($caretaker->can('encodeMoa', $occupied));
    }

    public function test_search_rooms_by_room_number(): void
    {
        $this->room(['room_no' => '201']);
        $this->room(['room_no' => '202']);
        $this->room(['room_no' => '305']);

        $response = $this->actingAs($this->owner)->get(route('rooms.index', ['search' => '20']))->assertOk();
        $response->assertSee('Room 201');
        $response->assertSee('Room 202');
        $response->assertDontSee('Room 305');

        $responseSpecific = $this->actingAs($this->owner)->get(route('rooms.index', ['search' => '305']))->assertOk();
        $responseSpecific->assertSee('Room 305');
        $responseSpecific->assertDontSee('Room 201');
        $responseSpecific->assertDontSee('Room 202');
        // Clear search button is rendered when search is active
        $responseSpecific->assertSee('id="room-search-clear"', false);
    }

    public function test_filter_rooms_by_status(): void
    {
        $this->room(['room_no' => '101', 'status' => 'Vacant']);
        $this->room(['room_no' => '102', 'status' => 'Occupied']);

        // Vacant only
        $resVacant = $this->actingAs($this->owner)->get(route('rooms.index', ['status' => 'Vacant']))->assertOk();
        $resVacant->assertSee('101');
        $resVacant->assertDontSee('102');

        // Occupied only
        $resOccupied = $this->actingAs($this->owner)->get(route('rooms.index', ['status' => 'Occupied']))->assertOk();
        $resOccupied->assertSee('102');
        $resOccupied->assertDontSee('101');

        // All status
        $resAll = $this->actingAs($this->owner)->get(route('rooms.index', ['status' => 'All']))->assertOk();
        $resAll->assertSee('101');
        $resAll->assertSee('102');
    }

    public function test_filter_rooms_by_type(): void
    {
        $this->room(['room_no' => '101', 'room_type' => 'Basic']);
        $this->room(['room_no' => '102', 'room_type' => 'Aircon']);

        // Basic only
        $resBasic = $this->actingAs($this->owner)->get(route('rooms.index', ['type' => 'Basic']))->assertOk();
        $resBasic->assertSee('101');
        $resBasic->assertDontSee('102');

        // Aircon only
        $resAircon = $this->actingAs($this->owner)->get(route('rooms.index', ['type' => 'Aircon']))->assertOk();
        $resAircon->assertSee('102');
        $resAircon->assertDontSee('101');
    }

    public function test_rooms_pagination_and_query_string_retention(): void
    {
        // Create 12 rooms
        for ($i = 1; $i <= 12; $i++) {
            $this->room([
                'room_no' => sprintf('2%02d', $i),
                'room_type' => $i % 2 === 0 ? 'Aircon' : 'Basic',
                'status' => 'Vacant',
            ]);
        }

        // Option 2: All records load on single page without page offsets hiding records
        $res = $this->actingAs($this->owner)->get(route('rooms.index'))->assertOk();
        $res->assertDontSee('Page 1 of');
        $res->assertSee('201');
        $res->assertSee('208');
        $res->assertSee('209');
        $res->assertSee('212');

        // With filter and search, query parameters apply directly
        $filtered = $this->actingAs($this->owner)->get(route('rooms.index', [
            'type' => 'Aircon',
            'search' => '2',
        ]))->assertOk();

        // 6 Aircon rooms (202, 204, 206, 208, 210, 212) all match and render on page
        $filtered->assertSee('202');
        $filtered->assertSee('212');
        $filtered->assertDontSee('201');
    }

    public function test_empty_search_results_shows_clear_filters_action(): void
    {
        $this->room(['room_no' => '201']);

        $response = $this->actingAs($this->owner)->get(route('rooms.index', ['search' => '999']))->assertOk();
        $response->assertSee('No rooms found matching your search and filter criteria.');
        $response->assertSee('Clear all filters');
    }

    public function test_owner_can_disable_room_for_renting_and_enable_again(): void
    {
        $room = $this->room(['room_no' => '301', 'is_rentable' => true]);

        // Disable room via toggle
        $this->actingAs($this->owner)
            ->patch(route('rooms.toggle-rentable', $room), ['disabled_reason' => 'Renovation in progress'])
            ->assertRedirect();

        $room->refresh();
        $this->assertFalse($room->isRentable());
        $this->assertSame('Renovation in progress', $room->disabled_reason);
        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Room changed',
            'record_id' => $room->room_id,
            'details' => 'Room disabled for renting',
        ]);

        // Re-enable room
        $this->actingAs($this->owner)
            ->patch(route('rooms.toggle-rentable', $room))
            ->assertRedirect();

        $room->refresh();
        $this->assertTrue($room->isRentable());
        $this->assertNull($room->disabled_reason);

        // Manager cannot toggle rentable state
        $manager = User::factory()->role('Manager')->create();
        $this->actingAs($manager)
            ->patch(route('rooms.toggle-rentable', $room))
            ->assertForbidden();
    }

    public function test_disabled_room_hides_encode_moa_and_prevents_leasing(): void
    {
        $room = $this->room(['room_no' => '302', 'status' => 'Vacant', 'is_rentable' => false, 'disabled_reason' => 'Ceiling repair']);

        // Policy denies encodeMoa
        $this->assertFalse($this->owner->can('encodeMoa', $room));

        // Index page does not render Encode MOA link for disabled room, but displays Disabled badge
        $response = $this->actingAs($this->owner)->get(route('rooms.index'))->assertOk();
        $response->assertDontSee(route('leases.create', ['room_id' => $room->room_id]), false);
        $response->assertSee('Disabled (Out)');
        $response->assertSee('Ceiling repair');

        // Filter by Disabled status displays this room
        $filterResponse = $this->actingAs($this->owner)->get(route('rooms.index', ['status' => 'Disabled']))->assertOk();
        $filterResponse->assertSee('Room 302');
    }

    public function test_occupied_room_cannot_be_disabled_via_toggle_or_update(): void
    {
        $occupiedRoom = $this->room(['room_no' => '401', 'status' => 'Occupied', 'is_rentable' => true]);

        // Attempting to toggle rentable on occupied room returns session error and keeps room rentable
        $this->actingAs($this->owner)
            ->patch(route('rooms.toggle-rentable', $occupiedRoom))
            ->assertSessionHasErrors('room');

        $occupiedRoom->refresh();
        $this->assertTrue($occupiedRoom->isRentable());

        // Attempting to update occupied room with is_rentable = 0 fails validation
        $this->actingAs($this->owner)
            ->put(route('rooms.update', $occupiedRoom), $this->validRoom([
                'room_no' => '401',
                'is_rentable' => '0',
            ]))
            ->assertSessionHasErrors('is_rentable');

        $occupiedRoom->refresh();
        $this->assertTrue($occupiedRoom->isRentable());

        // Modal for occupied room does not render the Disable Room button
        $response = $this->actingAs($this->owner)->get(route('rooms.index'))->assertOk();
        $response->assertDontSee('Disable Room 401');
        $response->assertDontSee(route('rooms.toggle-rentable', $occupiedRoom), false);
    }

    public function test_table_consolidates_rental_availability_into_unit_status(): void
    {
        $vacantRoom = $this->room(['room_no' => '101', 'status' => 'Vacant', 'is_rentable' => true]);
        $disabledRoom = $this->room(['room_no' => '102', 'status' => 'Vacant', 'is_rentable' => false, 'disabled_reason' => 'Water leak']);
        $occupiedRoom = $this->room(['room_no' => '103', 'status' => 'Occupied', 'is_rentable' => true]);

        $response = $this->actingAs($this->owner)->get(route('rooms.index'))->assertOk();

        // Unit Status column exists, separate Rental Availability column does not
        $response->assertSee('<th>Unit Status</th>', false);
        $response->assertDontSee('<th>Rental Availability</th>', false);
        $response->assertDontSee('Rental Availability');

        // Status badges render appropriately
        $response->assertSee('Vacant');
        $response->assertSee('Disabled (Out)');
        $response->assertSee('Occupied');
    }

    public function test_add_and_edit_modals_rendered_for_owner(): void
    {
        $room = $this->room(['room_no' => '303']);

        $response = $this->actingAs($this->owner)->get(route('rooms.index'))->assertOk();
        $response->assertSee('id="createRoomModal"', false);
        $response->assertSee('id="editRoomModal"', false);
        $response->assertSee('openCreateRoomModal()', false);
        $response->assertSee('openEditRoomModal(', false);

        // Caretaker does not see create or edit modals
        $caretaker = User::factory()->role('Caretaker')->create();
        $caretakerResponse = $this->actingAs($caretaker)->get(route('rooms.index'))->assertOk();
        $caretakerResponse->assertDontSee('id="createRoomModal"', false);
        $caretakerResponse->assertDontSee('id="editRoomModal"', false);
    }

    public function test_realtime_room_number_validation_and_check_endpoint(): void
    {
        $r1 = $this->room(['room_no' => '201']);
        $this->room(['room_no' => '202']);

        // Check availability endpoint returns exists=true for used room
        $this->actingAs($this->owner)->getJson(route('rooms.check-availability', ['room_no' => '201']))
            ->assertOk()
            ->assertJson([
                'available' => false,
                'exists' => true,
                'room_no' => '201',
            ]);

        // Returns available=true for unused room
        $this->actingAs($this->owner)->getJson(route('rooms.check-availability', ['room_no' => '299']))
            ->assertOk()
            ->assertJson([
                'available' => true,
                'exists' => false,
                'room_no' => '299',
            ]);

        // Excluding own id when editing returns available=true
        $this->actingAs($this->owner)->getJson(route('rooms.check-availability', ['room_no' => '201', 'exclude_id' => $r1->room_id]))
            ->assertOk()
            ->assertJson([
                'available' => true,
                'exists' => false,
            ]);

        // Index page renders real-time validation elements and functions
        $response = $this->actingAs($this->owner)->get(route('rooms.index'))->assertOk();
        $response->assertSee('create_room_no_feedback', false);
        $response->assertSee('edit_room_no_feedback', false);
        $response->assertSee('validateCreateRoomNoRealtime', false);

        // Standalone create page renders feedback container
        $createResponse = $this->actingAs($this->owner)->get(route('rooms.create'))->assertOk();
        $createResponse->assertSee('standalone_room_no_feedback', false);
    }

    public function test_create_room_modal_and_form_omit_manual_max_occupants_field(): void
    {
        $room = $this->room(['room_no' => '305']);

        // Index page has create modal WITHOUT create_max_occupants input, but edit modal HAS edit_max_occupants
        $indexResponse = $this->actingAs($this->owner)->get(route('rooms.index'))->assertOk();
        $indexResponse->assertDontSee('id="create_max_occupants"', false);
        $indexResponse->assertSee('id="edit_max_occupants"', false);

        // Standalone create page does NOT have max_occupants select/input
        $createResponse = $this->actingAs($this->owner)->get(route('rooms.create'))->assertOk();
        $createResponse->assertDontSee('id="max_occupants"', false);
        $createResponse->assertDontSee('name="max_occupants"', false);

        // Standalone edit page DOES have max_occupants select
        $editResponse = $this->actingAs($this->owner)->get(route('rooms.edit', $room))->assertOk();
        $editResponse->assertSee('id="max_occupants"', false);
        $editResponse->assertSee('name="max_occupants"', false);
    }

    public function test_creating_a_room_omits_manual_max_occupants_and_defaults_to_settings_capacity(): void
    {
        // 1. Post without max_occupants - defaults to 2
        $this->actingAs($this->owner)->post('/rooms', [
            'room_no' => '501',
            'room_type' => 'Basic',
            'is_metered' => '0',
            'monthly_rent' => '3000.00',
        ])->assertRedirect('/rooms')->assertSessionHas('status', 'Room 501 was added.');

        $room1 = Room::where('room_no', '501')->firstOrFail();
        $this->assertSame(2, $room1->max_occupants);

        // 2. Change global ceiling default_room_capacity to 3 in settings
        $setting = \App\Models\Setting::first();
        if ($setting) {
            $setting->update(['default_room_capacity' => 3]);
        } else {
            \App\Models\Setting::create(['default_room_capacity' => 3, 'updated_by' => $this->owner->user_id] + \App\Models\Setting::DEFAULTS);
        }

        $this->actingAs($this->owner)->post('/rooms', [
            'room_no' => '502',
            'room_type' => 'Aircon',
            'is_metered' => '1',
            'monthly_rent' => '3600.00',
        ])->assertRedirect('/rooms')->assertSessionHas('status', 'Room 502 was added.');

        $room2 = Room::where('room_no', '502')->firstOrFail();
        $this->assertSame(3, $room2->max_occupants);
    }
}
