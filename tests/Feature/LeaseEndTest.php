<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Lease;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

class LeaseEndTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private User $manager;

    private User $caretaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
        $this->manager = User::factory()->role('Manager')->create();
        $this->caretaker = User::factory()->role('Caretaker')->create();
    }

    public function test_caretaker_cannot_end_lease(): void
    {
        $room = $this->makeRoom('201');
        $lease = $this->makeLease($room, $this->owner);

        $this->actingAs($this->caretaker)
            ->patch(route('leases.end', $lease), [
                'actual_end_date' => today()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame('Active', $lease->refresh()->status);
        $this->assertSame('Occupied', $room->refresh()->status);
    }

    public function test_unauthenticated_user_cannot_end_lease(): void
    {
        $room = $this->makeRoom('201');
        $lease = $this->makeLease($room, $this->owner);

        $this->patch(route('leases.end', $lease), [
            'actual_end_date' => today()->toDateString(),
        ])->assertRedirect(route('login'));
    }

    public function test_manager_can_end_lease_atomically_vacating_room_and_logging_audit(): void
    {
        $room = $this->makeRoom('201');
        $lease = $this->makeLease($room, $this->owner);
        $room->update(['expected_vacant_date' => today()->addDays(15)]);

        $actualEndDate = today()->subDays(2)->toDateString();
        $notes = 'Tenant relocated for job transfer. Deposit settled verbally offline.';

        $response = $this->actingAs($this->manager)
            ->patch(route('leases.end', $lease), [
                'actual_end_date' => $actualEndDate,
                'termination_notes' => $notes,
            ]);

        $response->assertRedirect(route('leases.show', $lease))
            ->assertSessionHas('status', "Lease ended. Room {$room->room_no} is now Vacant.");

        // Assert lease and room state
        $this->assertSame('Ended', $lease->refresh()->status);
        $this->assertSame('Ended', $lease->displayStatus());
        $this->assertSame('Vacant', $room->refresh()->status);
        $this->assertNull($room->expected_vacant_date);

        // Assert audit log entry
        $audit = AuditLog::where('table_name', 'tbl_lease')
            ->where('record_id', $lease->lease_id)
            ->where('action', 'Lease ended and room marked vacant')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->manager->user_id, $audit->user_id);
        $this->assertStringContainsString("Actual move-out date: {$actualEndDate}", $audit->details);
        $this->assertStringContainsString("Notes: {$notes}", $audit->details);
    }

    public function test_ending_lease_defaults_actual_end_date_to_today_when_omitted(): void
    {
        $room = $this->makeRoom('202');
        $lease = $this->makeLease($room, $this->owner);

        $this->actingAs($this->owner)
            ->patch(route('leases.end', $lease), [])
            ->assertRedirect(route('leases.show', $lease));

        $this->assertSame('Ended', $lease->refresh()->status);
        $this->assertSame('Vacant', $room->refresh()->status);

        $audit = AuditLog::where('table_name', 'tbl_lease')
            ->where('record_id', $lease->lease_id)
            ->where('action', 'Lease ended and room marked vacant')
            ->first();

        $today = now()->toDateString();
        $this->assertStringContainsString("Actual move-out date: {$today}", $audit->details);
    }

    public function test_cannot_end_a_lease_that_is_not_active(): void
    {
        $room = $this->makeRoom('201');
        $lease = $this->makeLease($room, $this->owner);
        $lease->update(['status' => 'Ended']);

        $this->actingAs($this->manager)
            ->patch(route('leases.end', $lease), [])
            ->assertSessionHasErrors('lease');
    }

    public function test_newly_vacated_room_is_immediately_available_for_new_moa(): void
    {
        $room = $this->makeRoom('201');
        $lease = $this->makeLease($room, $this->owner, null, 'Previous Tenant');

        // End the lease
        $this->actingAs($this->manager)
            ->patch(route('leases.end', $lease), [])
            ->assertSessionHasNoErrors();

        $room->refresh();
        $this->assertSame('Vacant', $room->status);
        $this->assertTrue($room->isAvailable());

        // Check rooms index page shows "Encode MOA" button for the room
        $this->actingAs($this->manager)
            ->get(route('rooms.index'))
            ->assertOk()
            ->assertSee("Room {$room->room_no}")
            ->assertSee('Encode MOA');

        // New MOA can be encoded for the room
        $newTenant = Tenant::create([
            'full_name' => 'Next Tenant',
            'contact_no' => '0918 555 9999',
            'valid_id_type' => "Driver's License",
            'valid_id_no' => 'N01-23-456789',
        ]);
        $this->actingAs($this->manager)
            ->get(route('leases.create', ['room_id' => $room->room_id, 'tenant_id' => $newTenant->tenant_id]))
            ->assertOk()
            ->assertSee("Room {$room->room_no}")
            ->assertSee('Next Tenant');
    }

    public function test_standalone_moveout_routes_return_404(): void
    {
        $this->actingAs($this->owner)->get('/moveouts')->assertNotFound();
        $this->actingAs($this->owner)->get('/moveouts/create')->assertNotFound();
        $this->actingAs($this->owner)->get('/moveouts/1')->assertNotFound();
        $this->actingAs($this->owner)->get('/reports/moveouts')->assertNotFound();
    }
}
