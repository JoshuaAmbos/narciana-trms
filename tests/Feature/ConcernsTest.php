<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Concern;
use App\Models\Lease;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

class ConcernsTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private User $manager;

    private User $caretaker;

    private Room $room;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
        $this->manager = User::factory()->role('Manager')->create();
        $this->caretaker = User::factory()->role('Caretaker')->create();
        $this->room = $this->makeRoom('206');
        $this->lease = $this->makeLease($this->room, $this->manager);
        Storage::fake('public');
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'category' => 'Professional repair', 'room_id' => $this->room->room_id, 'lease_id' => $this->lease->lease_id,
            'reported_at' => now()->subHour()->format('Y-m-d\TH:i'), 'channel' => 'Group chat', 'priority' => 'Urgent',
            'description' => 'Tripping breaker', 'assigned_to' => $this->manager->user_id, 'status' => 'Open',
        ], $overrides);
    }

    private function log(array $overrides = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->caretaker)->post(route('concerns.store'), $this->form($overrides));
    }

    // ---------- Logging ----------

    public function test_every_role_can_log_a_concern(): void
    {
        foreach ([$this->owner, $this->manager, $this->caretaker] as $user) {
            $this->log([], $user)->assertSessionHasNoErrors();
        }

        $this->assertSame(3, Concern::count());
        $concern = Concern::first();
        $this->assertSame('Open', $concern->status);
        $this->assertNull($concern->resolved_at);
        $this->assertFalse($concern->charged_to_tenant);
        $this->assertSame(3, AuditLog::where('action', 'Concern logged')->count());
    }

    public function test_shared_area_needs_no_room_but_other_categories_do(): void
    {
        $this->log(['category' => 'Shared area', 'room_id' => '', 'lease_id' => '', 'description' => 'Clogged drain, 2nd-floor CR'])
            ->assertSessionHasNoErrors();
        $this->assertNull(Concern::first()->room_id);
        $this->actingAs($this->caretaker)->get(route('concerns.index'))->assertSee('Shared area');

        $this->log(['room_id' => '', 'lease_id' => ''])
            ->assertSessionHasErrors(['room_id' => 'Choose the room (only a shared-area concern can have no room).']);
    }

    public function test_tenant_must_rent_the_chosen_room(): void
    {
        $other = $this->makeRoom('207');

        $this->log(['room_id' => $other->room_id])->assertSessionHasErrors(['lease_id' => 'This tenant does not rent the chosen room.']);
    }

    public function test_validation(): void
    {
        $this->log(['description' => '', 'category' => 'Noise', 'channel' => 'Email', 'priority' => 'High',
            'reported_at' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['description', 'category', 'channel', 'priority', 'reported_at']);
    }

    // ---------- Cost and charge: Owner/Manager only ----------

    public function test_caretaker_cannot_set_cost_or_charge_even_by_posting_them(): void
    {
        $this->log(['status' => 'Resolved', 'resolution' => 'Replaced', 'cost' => '500', 'charged_to_tenant' => '1'])->assertSessionHasNoErrors();

        $concern = Concern::first();
        $this->assertNull($concern->cost);
        $this->assertFalse($concern->charged_to_tenant);
        $this->assertSame(0, AuditLog::where('action', 'Concern charged')->count());

        $this->actingAs($this->caretaker)->get(route('concerns.edit', $concern))->assertSee('Only the Owner or Manager can set the cost');
    }

    public function test_manager_charges_the_tenant_and_it_is_audited(): void
    {
        $this->log(['status' => 'Resolved', 'resolution' => 'Replaced door knob', 'cost' => '350', 'charged_to_tenant' => '1', 'category' => 'Damage'], $this->manager)
            ->assertSessionHasNoErrors();

        $concern = Concern::first();
        $this->assertSame('350.00', $concern->cost);
        $this->assertTrue($concern->charged_to_tenant);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Concern charged', 'record_id' => $concern->concern_id]);
    }

    public function test_charging_needs_a_tenant_and_a_cost(): void
    {
        $this->log(['charged_to_tenant' => '1', 'cost' => ''], $this->owner)->assertSessionHasErrors(['cost' => 'Enter the cost to charge to the tenant.']);
        $this->log(['charged_to_tenant' => '1', 'cost' => '100', 'lease_id' => ''], $this->owner)->assertSessionHasErrors('charged_to_tenant');
    }

    // ---------- Updating and resolving ----------

    public function test_anyone_can_resolve_and_resolved_at_is_filled_in(): void
    {
        $this->log([], $this->manager);
        $concern = Concern::first();

        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern), $this->form(['status' => 'Resolved']))
            ->assertSessionHasErrors(['resolution' => 'Describe how the concern was resolved.']);

        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern), $this->form(['status' => 'Resolved', 'resolution' => 'Electrician replaced the breaker']))
            ->assertRedirect(route('concerns.show', $concern));

        $concern->refresh();
        $this->assertSame('Resolved', $concern->status);
        $this->assertNotNull($concern->resolved_at);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Concern resolved', 'record_id' => $concern->concern_id, 'user_id' => $this->caretaker->user_id]);
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Concern updated', 'record_id' => $concern->concern_id]);

        // Reopened: resolved_at is cleared.
        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern), $this->form(['status' => 'In progress', 'resolution' => '']));
        $this->assertNull($concern->refresh()->resolved_at);
    }

    public function test_caretaker_update_keeps_the_managers_charge(): void
    {
        $this->log(['status' => 'Resolved', 'resolution' => 'Fixed', 'cost' => '350', 'charged_to_tenant' => '1'], $this->manager);
        $concern = Concern::first();

        // The caretaker's form has the money fields disabled, so they are not sent.
        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern), $this->form(['status' => 'Resolved', 'resolution' => 'Fixed, checked again']));

        $concern->refresh();
        $this->assertSame('350.00', $concern->cost);
        $this->assertTrue($concern->charged_to_tenant);
    }

    public function test_billed_concern_is_locked(): void
    {
        $this->log(['status' => 'Resolved', 'resolution' => 'Fixed', 'cost' => '350', 'charged_to_tenant' => '1'], $this->manager);
        $concern = Concern::first();
        $bill = Bill::create(['lease_id' => $this->lease->lease_id, 'period_start' => '2026-06-25', 'due_date' => '2026-07-25',
            'rent_amount' => 3500, 'other_charges' => 350, 'total_amount' => 3850, 'status' => 'Unpaid', 'user_id' => $this->manager->user_id]);
        $concern->update(['bill_id' => $bill->bill_id]);

        $this->actingAs($this->owner)->put(route('concerns.update', $concern), $this->form([
            'status' => 'Open', 'cost' => '999', 'charged_to_tenant' => '0', 'description' => 'Corrected text',
        ]))->assertSessionHasNoErrors();

        $concern->refresh();
        $this->assertSame('Resolved', $concern->status);
        $this->assertSame('350.00', $concern->cost);
        $this->assertTrue($concern->charged_to_tenant);
        $this->assertSame('Corrected text', $concern->description, 'Other details can still be corrected');
    }

    // ---------- Photo ----------

    public function test_camera_photo_is_stored_shrunk_on_the_public_disk(): void
    {
        $this->log(['photo' => UploadedFile::fake()->image('camera.jpg', 4000, 3000)])->assertSessionHasNoErrors();

        $concern = Concern::first();
        $this->assertStringStartsWith('concerns/', $concern->photo_path);
        Storage::disk('public')->assertExists($concern->photo_path);

        [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get($concern->photo_path));
        $this->assertSame([1600, 1200, IMAGETYPE_JPEG], [$w, $h, $type]);

        $this->actingAs($this->caretaker)->get(route('concerns.show', $concern))->assertSee($concern->photoUrl(), false);
    }

    public function test_form_opens_the_tablet_camera(): void
    {
        $this->actingAs($this->caretaker)->get(route('concerns.create'))
            ->assertOk()->assertSee('accept="image/*" capture="environment"', false)->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('disabled selected>Choose a room…', false); // "no room" must be a deliberate choice
    }

    public function test_photo_size_and_type_limits(): void
    {
        $this->log(['photo' => UploadedFile::fake()->image('big.jpg')->size(6000)])->assertSessionHasErrors(['photo' => 'The photo must be 5 MB or smaller.']);
        $this->log(['photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->assertSame(0, Concern::count());
    }

    public function test_replacing_or_removing_a_photo_deletes_the_old_file(): void
    {
        $this->log(['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]);
        $concern = Concern::first();
        $first = $concern->photo_path;

        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern), $this->form(['photo' => UploadedFile::fake()->image('b.png', 800, 600)]));
        $second = $concern->refresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern), $this->form(['remove_photo' => '1']));
        $this->assertNull($concern->refresh()->photo_path);
        Storage::disk('public')->assertMissing($second);
    }

    // ---------- Lists ----------

    public function test_room_history_with_repeated_issue_note(): void
    {
        $this->log(['description' => 'Tripping breaker again']);
        $this->log(['description' => 'Clogged sink', 'category' => 'Maintenance', 'status' => 'Resolved', 'resolution' => 'Unclogged']);
        $this->log(['description' => 'Tripping breaker', 'status' => 'Resolved', 'resolution' => 'Reset']);
        $this->log(['description' => 'Other room issue', 'room_id' => $this->makeRoom('301')->room_id, 'lease_id' => '']);

        $this->actingAs($this->caretaker)->get(route('concerns.index', ['room' => $this->room->room_id]))
            ->assertOk()
            ->assertSee('History of Room 206')
            ->assertSee('Professional repair reported 2 times')
            ->assertSee('Clogged sink')           // resolved ones are part of the history
            ->assertDontSee('Other room issue');
    }

    public function test_main_list_shows_unresolved_first_with_urgent_on_top(): void
    {
        $this->log(['description' => 'Routine bulb', 'priority' => 'Routine', 'category' => 'Maintenance', 'reported_at' => now()->subMinutes(10)->format('Y-m-d\TH:i')]);
        $this->log(['description' => 'Urgent breaker', 'reported_at' => now()->subDay()->format('Y-m-d\TH:i')]);
        $this->log(['description' => 'Fixed already', 'status' => 'Resolved', 'resolution' => 'Done']);

        $this->actingAs($this->caretaker)->get(route('concerns.index'))
            ->assertSeeInOrder(['Urgent breaker', 'Routine bulb'])
            ->assertDontSee('Fixed already');
        $this->actingAs($this->caretaker)->get(route('concerns.index', ['status' => 'all']))->assertSee('Fixed already');
    }

    public function test_guests_cannot_see_concerns(): void
    {
        $this->get(route('concerns.index'))->assertRedirect('/login');
    }
}
