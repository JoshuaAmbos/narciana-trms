<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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
                ->assertOk()->assertSee('Room availability')->assertSee('201')->assertSee('Log an inquiry');
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

    public function test_vacating_room_shows_its_expected_vacant_date(): void
    {
        $this->room(['status' => 'Vacating', 'expected_vacant_date' => '2026-10-15']);

        $this->actingAs($this->owner)->get('/rooms')->assertSee('Vacating')->assertSee('15 Oct 2026');
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
}
