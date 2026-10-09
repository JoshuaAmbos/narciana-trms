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
use Illuminate\Support\Facades\Auth;
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
        // Photos go to the private 'local' disk, faked in Tests\TestCase.
    }

    private function form(array $overrides = []): array
    {
        return array_merge([
            'category' => 'Electrical', 'room_id' => $this->room->room_id, 'lease_id' => $this->lease->lease_id,
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

    public function test_any_category_can_be_in_a_shared_area_and_cleanliness_always_is(): void
    {
        $this->log(['category' => 'Plumbing', 'room_id' => 'shared', 'lease_id' => '', 'description' => 'Clogged drain, 2nd-floor CR'])
            ->assertSessionHasNoErrors();
        $this->assertNull(Concern::first()->room_id);
        $this->actingAs($this->caretaker)->get(route('concerns.index'))->assertSee('Shared area');

        // No choice made at all (the "Choose a room…" prompt sends nothing).
        $this->log(['room_id' => null, 'lease_id' => ''])
            ->assertSessionHasErrors(['room_id' => 'Choose the room, or "No room (shared area)".']);

        // Cleanliness is about shared areas only.
        $this->log(['category' => 'Cleanliness (shared areas)', 'lease_id' => ''])
            ->assertSessionHasErrors('room_id');
        $this->log(['category' => 'Cleanliness (shared areas)', 'room_id' => 'shared', 'lease_id' => ''])->assertSessionHasNoErrors();

        // A shared-area concern has no tenant.
        $this->log(['room_id' => 'shared'])->assertSessionHasErrors('lease_id');
    }

    public function test_the_twelve_categories_with_their_descriptions(): void
    {
        $this->assertSame(['Plumbing', 'Electrical', 'Lights', 'Aircon', 'Door/Lock/Keys', 'Water supply', 'Pest control',
            'Noise/Disturbance', 'Cleanliness (shared areas)', 'Damage to room or furniture', 'Billing concern', 'Other'],
            array_keys(Concern::CATEGORIES));

        $page = $this->actingAs($this->caretaker)->get(route('concerns.create'))->assertOk();
        foreach (Concern::CATEGORIES as $name => $help) {
            $page->assertSee('<option value="'.e($name).'" data-help="'.e($help).'"', false);
        }
        // "Tripping breaker" is explained in plain words.
        $page->assertSee('the breaker cuts the power because of an overload')->assertSee('or a short circuit');

        foreach (array_keys(Concern::CATEGORIES) as $category) {
            $this->log(['category' => $category, 'room_id' => 'shared', 'lease_id' => ''], $this->manager)->assertSessionHasNoErrors();
        }
        $this->assertSame(12, Concern::count());
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
        $this->log(['status' => 'Resolved', 'resolution' => 'Replaced door knob', 'cost' => '350', 'charged_to_tenant' => '1', 'category' => 'Door/Lock/Keys'], $this->manager)
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

    public function test_timeline_records_who_reported_started_and_resolved(): void
    {
        $this->travelTo(now()->setTime(8, 0));
        $reported = ['reported_at' => now()->subHour()->format('Y-m-d\TH:i')]; // 7:00, kept on every update
        $this->log($reported, $this->caretaker);
        $concern = Concern::first();
        $this->assertSame($this->caretaker->user_id, $concern->reported_by);
        $this->assertNull($concern->started_at);

        $this->travel(2)->hours();
        $this->actingAs($this->manager)->put(route('concerns.update', $concern), $this->form($reported + ['status' => 'In progress']))->assertSessionHasNoErrors();
        $concern->refresh();
        $this->assertSame($this->manager->user_id, $concern->started_by);
        $this->assertSame('10:00', $concern->started_at->format('H:i'));
        $this->assertDatabaseHas('tbl_audit_log', ['action' => 'Concern started', 'record_id' => $concern->concern_id]);

        $this->travel(3)->hours();
        $this->actingAs($this->owner)->put(route('concerns.update', $concern), $this->form($reported + ['status' => 'Resolved', 'resolution' => 'Breaker replaced']));
        $concern->refresh();
        $this->assertSame($this->owner->user_id, $concern->resolved_by);
        $this->assertSame('13:00', $concern->resolved_at->format('H:i'));
        $this->assertSame('10:00', $concern->started_at->format('H:i'), 'Starting time is kept');

        $this->actingAs($this->caretaker)->get(route('concerns.show', $concern))->assertOk()
            ->assertSeeInOrder(['Reported', '7:00 AM', 'by Test Caretaker (Caretaker)',
                'In progress', '10:00 AM', 'by Test Manager (Manager)',
                'Resolved', '1:00 PM', 'by Test Owner (Owner)']);

        // Back to Open clears the later steps.
        $this->actingAs($this->owner)->put(route('concerns.update', $concern), $this->form($reported + ['status' => 'Open', 'resolution' => '']));
        $concern->refresh();
        $this->assertNull($concern->started_at);
        $this->assertNull($concern->resolved_by);
    }

    public function test_resolved_directly_from_open_shows_in_progress_as_skipped(): void
    {
        $this->log(['status' => 'Resolved', 'resolution' => 'Reset the breaker'], $this->manager);

        $this->actingAs($this->manager)->get(route('concerns.show', Concern::first()))->assertSee('skipped (resolved directly)');
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

    // ---------- Photos (private, decision 18) ----------

    public function test_camera_photo_is_stored_shrunk_in_private_storage(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $this->log(['photo' => UploadedFile::fake()->image('camera.jpg', 4000, 3000)])->assertSessionHasNoErrors();

        $concern = Concern::first();
        $this->assertStringStartsWith('concerns/', $concern->photo_path);
        Storage::disk('local')->assertExists($concern->photo_path);

        [$w, $h, $type] = getimagesizefromstring(Storage::disk('local')->get($concern->photo_path));
        $this->assertSame([1600, 1200, IMAGETYPE_JPEG], [$w, $h, $type]);

        $url = route('concerns.photo', [$concern, 'reported']);
        $this->assertSame($url, $concern->photoUrl());
        $this->actingAs($this->caretaker)->get(route('concerns.show', $concern))->assertSee($url, false);
        $this->actingAs($this->caretaker)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_photos_are_not_public(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $this->log(['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]);
        $concern = Concern::first();

        Auth::logout();
        $this->get(route('concerns.photo', [$concern, 'reported']))->assertRedirect('/login');
        $this->assertStringNotContainsString('/storage/', $concern->photoUrl());

        $this->actingAs($this->caretaker)->get(route('concerns.photo', [$concern, 'resolved']))->assertNotFound(); // none yet
        $this->actingAs($this->caretaker)->get('/concerns/'.$concern->concern_id.'/photo/other')->assertNotFound();
    }

    public function test_photo_when_resolved_is_proof_of_the_fix(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $this->log(['photo' => UploadedFile::fake()->image('before.jpg', 800, 600)]);
        $concern = Concern::first();

        // Only together with Resolved.
        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern),
            $this->form(['status' => 'In progress', 'resolution_photo' => UploadedFile::fake()->image('after.jpg', 800, 600)]))
            ->assertSessionHasErrors(['resolution_photo' => 'Add the "fixed" photo when you mark the concern Resolved.']);

        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern),
            $this->form(['status' => 'Resolved', 'resolution' => 'Fixed', 'resolution_photo' => UploadedFile::fake()->image('after.png', 800, 600)]))
            ->assertSessionHasNoErrors();

        $concern->refresh();
        $this->assertNotNull($concern->photo_path, 'The "problem" photo is kept');
        Storage::disk('local')->assertExists($concern->resolution_photo_path);
        $this->actingAs($this->caretaker)->get(route('concerns.show', $concern))
            ->assertSee(route('concerns.photo', [$concern, 'reported']), false)
            ->assertSee(route('concerns.photo', [$concern, 'resolved']), false);
        $this->actingAs($this->caretaker)->get(route('concerns.photo', [$concern, 'resolved']))->assertOk();

        $old = $concern->resolution_photo_path;
        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern),
            $this->form(['status' => 'Resolved', 'resolution' => 'Fixed', 'remove_resolution_photo' => '1']));
        $this->assertNull($concern->refresh()->resolution_photo_path);
        Storage::disk('local')->assertMissing($old);
    }

    public function test_form_opens_the_tablet_camera(): void
    {
        $this->actingAs($this->caretaker)->get(route('concerns.create'))
            ->assertOk()->assertSee('accept="image/jpeg,image/png" capture="environment"', false)->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('disabled selected>Choose a room…', false) // "no room" must be a deliberate choice
            ->assertSee('<option value="shared"', false);
    }

    public function test_photo_size_and_type_limits(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $this->log(['photo' => UploadedFile::fake()->image('big.jpg')->size(6000)])->assertSessionHasErrors(['photo' => 'The photo must be 5 MB or smaller.']);
        $this->log(['photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->log(['photo' => UploadedFile::fake()->image('phone.webp', 800, 600)])->assertSessionHasErrors(['photo' => 'Use a JPG or PNG photo.']);
        $this->log(['status' => 'Resolved', 'resolution' => 'Done', 'resolution_photo' => UploadedFile::fake()->image('big.png')->size(6000)])
            ->assertSessionHasErrors(['resolution_photo' => 'The photo must be 5 MB or smaller.']);
        $this->assertSame(0, Concern::count());
    }

    public function test_replacing_or_removing_a_photo_deletes_the_old_file(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $this->log(['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]);
        $concern = Concern::first();
        $first = $concern->photo_path;

        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern), $this->form(['photo' => UploadedFile::fake()->image('b.png', 800, 600)]));
        $second = $concern->refresh()->photo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);

        $this->actingAs($this->caretaker)->put(route('concerns.update', $concern), $this->form(['remove_photo' => '1']));
        $this->assertNull($concern->refresh()->photo_path);
        Storage::disk('local')->assertMissing($second);
    }

    // ---------- Lists ----------

    public function test_room_history_with_repeated_issue_note(): void
    {
        $this->log(['description' => 'Tripping breaker again']);
        $this->log(['description' => 'Clogged sink', 'category' => 'Plumbing', 'status' => 'Resolved', 'resolution' => 'Unclogged']);
        $this->log(['description' => 'Tripping breaker', 'status' => 'Resolved', 'resolution' => 'Reset']);
        $this->log(['description' => 'Other room issue', 'room_id' => $this->makeRoom('301')->room_id, 'lease_id' => '']);

        $this->actingAs($this->caretaker)->get(route('concerns.index', ['room' => $this->room->room_id]))
            ->assertOk()
            ->assertSee('History of Room 206')
            ->assertSee('Electrical reported 2 times')
            ->assertSee('Clogged sink')           // resolved ones are part of the history
            ->assertDontSee('Other room issue');
    }

    public function test_main_list_shows_unresolved_first_with_urgent_on_top(): void
    {
        $this->log(['description' => 'Routine bulb', 'priority' => 'Routine', 'category' => 'Lights', 'reported_at' => now()->subMinutes(10)->format('Y-m-d\TH:i')]);
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

    public function test_universal_search_filters_by_room_category_and_keyword(): void
    {
        $room301 = $this->makeRoom('301');
        $this->log(['description' => 'Leaking faucet in bathroom', 'category' => 'Plumbing', 'room_id' => $this->room->room_id], $this->manager);
        $this->log(['description' => 'Busted ceiling light', 'category' => 'Lights', 'room_id' => $room301->room_id, 'lease_id' => ''], $this->manager);

        // Search by keyword
        $this->actingAs($this->manager)->get(route('concerns.index', ['search' => 'faucet']))
            ->assertOk()
            ->assertSee('Leaking faucet')
            ->assertDontSee('Busted ceiling light');

        // Search by category
        $this->actingAs($this->manager)->get(route('concerns.index', ['search' => 'Lights']))
            ->assertOk()
            ->assertSee('Busted ceiling light')
            ->assertDontSee('Leaking faucet');

        // Search by room number
        $this->actingAs($this->manager)->get(route('concerns.index', ['search' => '301']))
            ->assertOk()
            ->assertSee('Busted ceiling light')
            ->assertDontSee('Leaking faucet');
    }

    public function test_no_standalone_open_buttons_and_rows_cards_trigger_modals(): void
    {
        $this->log(['description' => 'Door lock jammed'], $this->caretaker);
        $concern = Concern::first();

        $response = $this->actingAs($this->caretaker)->get(route('concerns.index'))->assertOk();

        // No standalone "Open" or "Show" button in the table or cards
        $response->assertDontSee('class="btn sec">Open<', false);
        $response->assertDontSee('class="btn">Open<', false);
        $response->assertDontSee('class="btn sec">Show<', false);

        // Clickable table row and mobile card present
        $response->assertSee('onclick="openDetailModal('.$concern->concern_id.')"', false);
        $response->assertSee('class="concern-row"', false);
        $response->assertSee('class="concern-card"', false);

        // In-page slide-up/popup modals present
        $response->assertSee('id="createConcernModal"', false);
        $response->assertSee('id="detailConcernModal"', false);
        $response->assertSee('id="updateConcernModal"', false);
    }

    public function test_responsive_portrait_cards_and_fab_button_present(): void
    {
        $this->log(['description' => 'No water on second floor', 'category' => 'Water supply'], $this->caretaker);

        $response = $this->actingAs($this->caretaker)->get(route('concerns.index'))->assertOk();

        // Floating Action Button (FAB) present with + icon and trigger
        $response->assertSee('class="concern-fab"', false);
        $response->assertSee('aria-label="Log New Concern"', false);
        $response->assertSee('onclick="openCreateConcernModal()"', false);

        // Stacked cards container present
        $response->assertSee('class="concern-cards"', false);
        $response->assertSee('class="concern-card-location"', false);
    }

    public function test_logging_and_updating_concern_from_index_redirects_to_index_with_flash(): void
    {
        $createData = $this->form(['from_index' => '1', 'description' => 'Wall crack reported']);
        $response = $this->actingAs($this->manager)->post(route('concerns.store'), $createData);
        $concern = Concern::latest('concern_id')->first();
        $response->assertRedirect(route('concerns.index', ['concern' => $concern->concern_id]))
            ->assertSessionHas('status', 'Concern logged.');

        $updateData = $this->form(['from_index' => '1', 'status' => 'Resolved', 'resolution' => 'Patched and repainted']);
        $this->actingAs($this->manager)->put(route('concerns.update', $concern), $updateData)
            ->assertRedirect(route('concerns.index', ['concern' => $concern->concern_id]))
            ->assertSessionHas('status', 'Concern updated.');
    }

    public function test_cost_recovery_flag_persists_for_billing_integration(): void
    {
        $createData = $this->form([
            'from_index' => '1',
            'status' => 'Resolved',
            'resolution' => 'Replaced broken window glass',
            'cost' => '750.00',
            'charged_to_tenant' => '1',
        ]);

        $this->actingAs($this->manager)->post(route('concerns.store'), $createData)->assertSessionHasNoErrors();

        $concern = Concern::latest('concern_id')->first();
        $this->assertTrue($concern->charged_to_tenant);
        $this->assertSame('750.00', $concern->cost);

        // Detail modal and cards have the charge reflected when viewing resolved or all concerns
        $this->actingAs($this->manager)->get(route('concerns.index', ['status' => 'Resolved']))
            ->assertOk()
            ->assertSee('Charge: ₱750.00')
            ->assertSee('Charge to Tenant: ₱750.00');
    }

    public function test_assigned_to_me_filter_and_switching_to_other_filters(): void
    {
        // One concern assigned to manager, one assigned to caretaker
        $this->log(['description' => 'Manager specific ticket', 'assigned_to' => $this->manager->user_id], $this->manager);
        $this->log(['description' => 'Caretaker specific ticket', 'assigned_to' => $this->caretaker->user_id], $this->manager);

        // Caretaker views "Assigned to me"
        $response = $this->actingAs($this->caretaker)->get(route('concerns.index', ['mine' => 1]))
            ->assertOk()
            ->assertSee('Caretaker specific ticket')
            ->assertDontSee('Manager specific ticket');

        // Other filter links on the page must NOT carry mine=1
        $response->assertDontSee('status=all&amp;mine=1', false);
        $response->assertDontSee('status=open&amp;mine=1', false);
        $response->assertDontSee('status=Open&amp;mine=1', false);

        // Clicking "All" filter loads all concerns without getting stuck on mine=1
        $this->actingAs($this->caretaker)->get(route('concerns.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('Caretaker specific ticket')
            ->assertSee('Manager specific ticket');
    }

    public function test_updating_concern_to_resolved_populates_map_and_opens_detail_modal_on_redirect(): void
    {
        $this->log(['description' => 'Leaky faucet in bathroom'], $this->manager);
        $concern = Concern::first();
        $this->assertSame('Open', $concern->status);

        $updateData = $this->form([
            'from_index' => '1',
            'concern_id' => $concern->concern_id,
            'status' => 'Resolved',
            'resolution' => 'Replaced faucet washer and valve',
        ]);

        $response = $this->actingAs($this->manager)->put(route('concerns.update', $concern), $updateData);
        $response->assertRedirect(route('concerns.index', ['concern' => $concern->concern_id]))
            ->assertSessionHas('status', 'Concern updated.');

        $concern->refresh();
        $this->assertSame('Resolved', $concern->status);
        $this->assertSame('Replaced faucet washer and valve', $concern->resolution);
        $this->assertNotNull($concern->resolved_at);

        // Follow redirect to index: default filter is "open" (unresolved),
        // but concernsMap must include the newly resolved concern so the modal auto-opens!
        $indexResponse = $this->actingAs($this->manager)->get(route('concerns.index', ['concern' => $concern->concern_id]));
        $indexResponse->assertOk();
        $concernsMap = $indexResponse->viewData('concernsMap');
        $this->assertArrayHasKey($concern->concern_id, $concernsMap);
        $this->assertSame('Resolved', $concernsMap[$concern->concern_id]['status']);
        $this->assertSame('Replaced faucet washer and valve', $concernsMap[$concern->concern_id]['resolution']);
        $indexResponse->assertSee("openDetailModal({$concern->concern_id})", false);
    }

    public function test_updating_concern_to_resolved_without_resolution_fails_validation_and_retains_update_modal(): void
    {
        $this->log(['description' => 'Light fixture broken'], $this->manager);
        $concern = Concern::first();

        $updateData = $this->form([
            'from_index' => '1',
            'concern_id' => $concern->concern_id,
            'status' => 'Resolved',
            'resolution' => '',
        ]);

        $response = $this->actingAs($this->manager)->from(route('concerns.index'))
            ->put(route('concerns.update', $concern), $updateData);

        $response->assertRedirect(route('concerns.index'))
            ->assertSessionHasErrors(['resolution' => 'Describe how the concern was resolved.']);

        // When visiting index with old input & validation error, the update modal is re-opened with error messages
        $followResponse = $this->actingAs($this->manager)->withSession([
            '_old_input' => $updateData,
            'errors' => session('errors'),
        ])->get(route('concerns.index'));

        $followResponse->assertOk();
        $followResponse->assertSee("openUpdateModal({$concern->concern_id}, true)", false);
        $followResponse->assertSee('Describe how the concern was resolved.');
    }
}
