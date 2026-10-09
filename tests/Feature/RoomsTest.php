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
            ->assertSee('+ Add room')->assertSee(route('rooms.edit', $room), false);

        foreach (['Manager', 'Caretaker'] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get('/rooms')
                ->assertDontSee('+ Add room')->assertDontSee(route('rooms.edit', $room), false);
        }
    }

    public function test_room_statuses_are_strictly_vacant_or_occupied(): void
    {
        $this->assertSame(['Vacant', 'Occupied'], Room::STATUSES);
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
        $this->actingAs($this->owner)->post('/rooms', $this->validRoom(['room_no' => 'B1', 'room_type' => 'Basic', 'is_metered' => '1']));
        $this->actingAs($this->owner)->post('/rooms', $this->validRoom(['room_no' => 'A1', 'room_type' => 'Aircon', 'is_metered' => null]));

        $this->assertTrue(Room::where('room_no', 'B1')->value('is_metered'));
        $this->assertFalse((bool) Room::where('room_no', 'A1')->value('is_metered'));
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
        // Create 12 rooms (page limit is 8 per page)
        for ($i = 1; $i <= 12; $i++) {
            $this->room([
                'room_no' => sprintf('2%02d', $i),
                'room_type' => $i % 2 === 0 ? 'Aircon' : 'Basic',
                'status' => 'Vacant',
            ]);
        }

        // Page 1 should have 8 rooms
        $page1 = $this->actingAs($this->owner)->get(route('rooms.index'))->assertOk();
        $page1->assertSee('Page 1 of 2');
        $page1->assertSee('201');
        $page1->assertSee('208');
        $page1->assertDontSee('209');

        // Page 2 should have remaining 4 rooms
        $page2 = $this->actingAs($this->owner)->get(route('rooms.index', ['page' => 2]))->assertOk();
        $page2->assertSee('Page 2 of 2');
        $page2->assertSee('209');
        $page2->assertSee('212');
        $page2->assertDontSee('201');

        // With filter and search, query parameters are retained in pagination links
        $filtered = $this->actingAs($this->owner)->get(route('rooms.index', [
            'type' => 'Aircon',
            'search' => '2',
        ]))->assertOk();

        // 6 Aircon rooms (202, 204, 206, 208, 210, 212) all match and fit on 1 page
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
}
