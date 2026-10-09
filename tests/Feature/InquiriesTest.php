<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiriesTest extends TestCase
{
    use RefreshDatabase;

    private User $caretaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caretaker = User::factory()->role('Caretaker')->create();
    }

    private function validInquiry(array $overrides = []): array
    {
        return array_merge([
            'prospect_name' => 'Sample Prospect A',
            'contact_no' => '0917 555 0142',
            'preferred_type' => 'Aircon',
            'no_of_occupants' => '1',
            'channel' => 'Walk-in',
            'inquiry_date' => today()->toDateString(),
        ], $overrides);
    }

    private function inquiry(array $attributes = []): Inquiry
    {
        return Inquiry::create($attributes + [
            'prospect_name' => 'Sample Prospect X', 'contact_no' => '0917 000 0000', 'preferred_type' => 'Any',
            'no_of_occupants' => 1, 'channel' => 'Call', 'via_representative' => false,
            'inquiry_date' => today()->toDateString(), 'status' => 'Waiting', 'user_id' => $this->caretaker->user_id,
        ]);
    }

    private function room(string $no, string $type, string $status = 'Vacant', int $max = 2): Room
    {
        return Room::create([
            'room_no' => $no, 'room_type' => $type, 'is_metered' => $type === 'Aircon', 'max_occupants' => $max,
            'monthly_rent' => 3000, 'status' => $status,
        ]);
    }

    // ---------- Logging ----------

    public function test_every_role_can_log_an_inquiry(): void
    {
        foreach (User::ROLES as $i => $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->post('/inquiries', $this->validInquiry(['prospect_name' => "Sample Prospect {$i}"]))
                ->assertRedirect('/inquiries')->assertSessionHas('status');

            $this->assertDatabaseHas('tbl_inquiry', [
                'prospect_name' => "Sample Prospect {$i}", 'status' => 'Waiting', 'user_id' => $user->user_id,
            ]);
        }
    }

    public function test_representative_checkbox_and_defaults(): void
    {
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry(['via_representative' => '1']));
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry(['prospect_name' => 'Sample Prospect B']));

        $this->assertTrue(Inquiry::where('prospect_name', 'Sample Prospect A')->value('via_representative'));
        $this->assertFalse((bool) Inquiry::where('prospect_name', 'Sample Prospect B')->value('via_representative'));
    }

    public function test_inquiry_validation(): void
    {
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'prospect_name' => '',
            'contact_no' => 'none',
            'preferred_type' => 'Suite',
            'no_of_occupants' => '0',
            'channel' => 'Email',
            'inquiry_date' => today()->addDay()->toDateString(),
        ]))->assertSessionHasErrors(['prospect_name', 'contact_no', 'preferred_type', 'no_of_occupants', 'channel', 'inquiry_date']);

        $this->assertSame(0, Inquiry::count());
    }

    public function test_rented_needs_a_room(): void
    {
        $inquiry = $this->inquiry();
        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry(['status' => 'Rented']))
            ->assertSessionHasErrors(['room_id' => 'Choose the room that was rented.']);

        $room = $this->room('203', 'Basic');
        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry(['status' => 'Rented', 'room_id' => $room->room_id]))
            ->assertSessionHasNoErrors();
    }

    public function test_intake_stores_room_id_as_null_regardless_of_payload(): void
    {
        $room = $this->room('203', 'Basic');
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'room_id' => $room->room_id,
        ]))->assertRedirect('/inquiries');

        $inquiry = Inquiry::latest('inquiry_id')->first();
        $this->assertNull($inquiry->room_id);
        $this->assertSame('Waiting', $inquiry->status);
    }

    public function test_marking_rented_does_not_change_the_room_status(): void
    {
        $room = $this->room('203', 'Basic');
        $inquiry = $this->inquiry();

        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry([
            'status' => 'Rented', 'room_id' => $room->room_id,
        ]))->assertRedirect('/inquiries');

        $this->assertSame('Rented', $inquiry->refresh()->status);
        $this->assertSame('Vacant', $room->refresh()->status);
    }

    public function test_occupancy_validation_blocks_values_greater_than_two(): void
    {
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry(['no_of_occupants' => '3']))
            ->assertSessionHasErrors(['no_of_occupants' => 'A room allows at most 2 occupants (1 or 2 occupants).']);

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry(['no_of_occupants' => '0']))
            ->assertSessionHasErrors('no_of_occupants');
    }

    // ---------- Bug 1: occupants must fit the room when Rented ----------

    public function test_rented_is_blocked_when_occupants_exceed_the_room_maximum(): void
    {
        $room = $this->room('3', 'Aircon', 'Vacant', 1);

        $inquiry = $this->inquiry(['no_of_occupants' => 2, 'preferred_type' => 'Aircon']);
        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry([
            'no_of_occupants' => '2', 'status' => 'Rented', 'room_id' => $room->room_id,
        ]))->assertSessionHasErrors(['no_of_occupants' => 'Room 3 allows at most 1 occupant; this inquiry has 2.']);

        $this->assertSame('Waiting', $inquiry->refresh()->status);
        $this->assertSame(1, Inquiry::count());
    }

    public function test_one_occupant_room_blocks_two_occupants(): void
    {
        $room = $this->room('9', 'Basic', 'Vacant', 1);
        $inquiry = $this->inquiry(['preferred_type' => 'Basic']);

        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry([
            'preferred_type' => 'Basic', 'no_of_occupants' => '2', 'status' => 'Rented', 'room_id' => $room->room_id,
        ]))->assertSessionHasErrors(['no_of_occupants' => 'Room 9 allows at most 1 occupant; this inquiry has 2.']);
    }

    public function test_three_occupants_can_still_be_waiting_or_declined_with_a_room_shown(): void
    {
        $room = $this->room('3', 'Aircon');

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'no_of_occupants' => '3', 'status' => 'Declined', 'room_id' => $room->room_id,
        ]))->assertSessionHasErrors('no_of_occupants');
    }

    // ---------- Bug 2: one pending Rented inquiry per room; only Vacant rooms ----------

    public function test_a_room_held_by_another_rented_inquiry_cannot_be_rented_again(): void
    {
        $room = $this->room('3', 'Aircon');
        $this->inquiry(['prospect_name' => 'Sample First', 'status' => 'Rented', 'room_id' => $room->room_id,
            'inquiry_date' => '2026-09-20']);

        $second = $this->inquiry(['prospect_name' => 'Sample Second']);
        $this->actingAs($this->caretaker)->put("/inquiries/{$second->inquiry_id}", $this->validInquiry([
            'status' => 'Rented', 'room_id' => $room->room_id,
        ]))->assertSessionHasErrors([
            'room_id' => 'Room 3 is already marked as rented to Sample First (inquiry of 20 Sep 2026) and its MOA has not been encoded yet.',
        ]);

        $this->assertSame(1, Inquiry::where('status', 'Rented')->count());
    }

    public function test_the_hold_ends_once_a_lease_is_encoded_for_the_room(): void
    {
        $room = $this->room('3', 'Aircon');
        $this->inquiry(['status' => 'Rented', 'room_id' => $room->room_id, 'inquiry_date' => '2026-09-20']);

        // MOA encoded for that room after the inquiry (Module 4 will do this and set the room Occupied;
        // here the room is set back to Vacant as if that tenant already moved out).
        $tenant = Tenant::create(['full_name' => 'Sample Tenant 1', 'contact_no' => '0917', 'valid_id_type' => 'ID', 'valid_id_no' => '1']);
        Lease::create([
            'tenant_id' => $tenant->tenant_id, 'room_id' => $room->room_id, 'moa_date' => '2026-09-22', 'move_in_date' => '2026-09-25',
            'due_day' => 25, 'monthly_rent' => 3000, 'min_stay_months' => 4, 'advance_amount' => 3000, 'deposit_maintenance' => 0,
            'deposit_damage' => 0, 'deposit_bills' => 0, 'deposit_keys' => 0, 'status' => 'Ended', 'user_id' => $this->caretaker->user_id,
        ]);

        $second = $this->inquiry();
        $this->actingAs($this->caretaker)->put("/inquiries/{$second->inquiry_id}", $this->validInquiry([
            'status' => 'Rented', 'room_id' => $room->room_id,
        ]))->assertSessionHasNoErrors();
    }

    public function test_only_vacant_rooms_can_be_rented(): void
    {
        $occupied = $this->room('7', 'Aircon', 'Occupied');
        $vacant = $this->room('8', 'Aircon', 'Vacant');
        $inquiry = $this->inquiry();

        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry([
            'status' => 'Rented', 'room_id' => $occupied->room_id,
        ]))->assertSessionHasErrors(['room_id' => 'Room 7 is Occupied. Only Vacant rooms can be marked as rented.']);

        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry([
            'status' => 'Rented', 'room_id' => $vacant->room_id,
        ]))->assertSessionHasNoErrors();
    }

    public function test_an_already_rented_inquiry_can_still_be_edited_after_its_room_becomes_occupied(): void
    {
        $room = $this->room('3', 'Aircon');
        $inquiry = $this->inquiry(['preferred_type' => 'Aircon', 'status' => 'Rented', 'room_id' => $room->room_id]);
        $room->update(['status' => 'Occupied']); // its MOA was encoded

        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry([
            'status' => 'Rented', 'room_id' => $room->room_id, 'remarks' => 'Moved in',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Moved in', $inquiry->refresh()->remarks);
    }

    public function test_room_dropdown_labels_a_held_room(): void
    {
        $room = $this->room('3', 'Aircon');
        $this->inquiry(['prospect_name' => 'Sample First', 'status' => 'Rented', 'room_id' => $room->room_id]);

        $this->actingAs($this->caretaker)->get('/inquiries')->assertSee('rented to Sample First, MOA pending');
    }

    public function test_type_mismatch_is_a_warning_not_an_error(): void
    {
        $basic = $this->room('1', 'Basic');
        $inquiry = $this->inquiry(['preferred_type' => 'Aircon']);

        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry([
            'preferred_type' => 'Aircon', 'status' => 'Rented', 'room_id' => $basic->room_id,
        ]))->assertSessionHasNoErrors()
            ->assertSessionHas('warning', 'Note: Room 1 is a Basic room, but the prospect asked for Aircon.');

        $this->assertSame(1, Inquiry::where('status', 'Rented')->count());
        $this->followRedirects($this->actingAs($this->caretaker)->get('/inquiries'))->assertOk();
    }

    public function test_no_warning_when_type_matches_or_any(): void
    {
        $basic = $this->room('1', 'Basic');
        $other = $this->room('2', 'Basic');
        $inquiry1 = $this->inquiry(['preferred_type' => 'Basic']);
        $inquiry2 = $this->inquiry(['preferred_type' => 'Any']);

        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry1->inquiry_id}", $this->validInquiry([
            'preferred_type' => 'Basic', 'status' => 'Rented', 'room_id' => $basic->room_id,
        ]))->assertSessionMissing('warning');

        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry2->inquiry_id}", $this->validInquiry([
            'prospect_name' => 'Sample B', 'preferred_type' => 'Any', 'status' => 'Rented', 'room_id' => $other->room_id,
        ]))->assertSessionMissing('warning');
    }

    // ---------- Bug 3 report: the log shows the room number, not the database ID ----------

    public function test_inquiry_log_shows_room_number_not_room_id(): void
    {
        $this->room('1', 'Basic');                    // room_id 1, room_no "1"
        $room = $this->room('305', 'Aircon');         // room_id 2, room_no "305"
        $this->inquiry(['status' => 'Rented', 'room_id' => $room->room_id, 'preferred_type' => 'Aircon']);

        $response = $this->actingAs($this->caretaker)->get('/inquiries')->assertOk();

        $response->assertSee('<td>305</td>', false);
        $response->assertDontSee('<td>'.$room->room_id.'</td>', false);
    }

    // ---------- Updating ----------

    public function test_update_keeps_who_logged_it(): void
    {
        $inquiry = $this->inquiry();
        $manager = User::factory()->role('Manager')->create();

        $this->actingAs($manager)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry([
            'status' => 'Referred', 'remarks' => 'Referred to a nearby boarding house',
        ]))->assertRedirect('/inquiries');

        $inquiry->refresh();
        $this->assertSame('Referred', $inquiry->status);
        $this->assertSame($this->caretaker->user_id, $inquiry->user_id);
    }

    // ---------- Waiting list and matching ----------

    /** Decision 19: Rooms shows availability only; the waiting list and "Waiting" column are gone, the data is not. */
    public function test_rooms_page_has_no_waiting_list_and_inquiries_are_kept(): void
    {
        $this->room('201', 'Basic');
        $this->room('208', 'Aircon', 'Occupied');
        $this->inquiry(['prospect_name' => 'Sample Newer', 'preferred_type' => 'Aircon', 'inquiry_date' => today()->subDays(1)->toDateString()]);
        $this->inquiry(['prospect_name' => 'Sample Older', 'preferred_type' => 'Basic', 'inquiry_date' => today()->subDays(5)->toDateString()]);
        $this->inquiry(['prospect_name' => 'Sample Declined', 'status' => 'Declined']);
        $before = Inquiry::orderBy('inquiry_id')->get()->toArray();

        $this->actingAs($this->caretaker)->get('/rooms')->assertOk()
            ->assertSee('Room availability')->assertSee('201')->assertSee('208')
            ->assertDontSee('Prospects waiting')->assertDontSee('<th>Waiting</th>', false)->assertDontSee('prospect')
            ->assertDontSee('Log an inquiry')->assertDontSee('Sample Older')->assertDontSee('Sample Newer');

        // Every inquiry is still there, unchanged, and listed on the Inquiries page under its status.
        $this->assertSame($before, Inquiry::orderBy('inquiry_id')->get()->toArray());
        $this->actingAs($this->caretaker)->get('/inquiries?status=Waiting')->assertOk()
            ->assertSeeInOrder(['Sample Older', 'Sample Newer'])->assertDontSee('Sample Declined');
    }

    public function test_inquiries_page_has_the_form_and_the_status_column(): void
    {
        $this->inquiry(['prospect_name' => 'Sample Referred', 'status' => 'Referred']);

        foreach (User::ROLES as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get('/inquiries')->assertOk()
                ->assertSee('Log an inquiry')->assertSee('action="'.route('inquiries.store').'"', false)
                ->assertSee('<label for="status">Status</label>', false)
                ->assertSeeInOrder(['Inquiry log', '<th>Status</th>', 'Sample Referred', 'Referred'], false)
                ->assertSee('Wants to be contacted when a room is available');
        }
        foreach (Inquiry::STATUSES as $status) {
            $this->actingAs($this->caretaker)->get('/inquiries')->assertSee('<option value="'.$status.'"', false);
        }
    }

    public function test_wants_to_be_contacted_checkbox(): void
    {
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry(['wants_contact' => '1']))->assertSessionHasNoErrors();
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry(['prospect_name' => 'Sample Prospect B']));

        $this->assertTrue(Inquiry::where('prospect_name', 'Sample Prospect A')->value('wants_contact'));
        $this->assertFalse(Inquiry::where('prospect_name', 'Sample Prospect B')->value('wants_contact'), 'Unticked by default');
        $this->actingAs($this->caretaker)->get('/inquiries')->assertSee('wants to be contacted');

        // Unticking on update clears it.
        $inquiry = Inquiry::where('prospect_name', 'Sample Prospect A')->first();
        $this->actingAs($this->caretaker)->put(route('inquiries.update', $inquiry), $this->validInquiry())->assertSessionHasNoErrors();
        $this->assertFalse($inquiry->refresh()->wants_contact);
    }

    public function test_inquiry_log_filters_by_status(): void
    {
        $this->inquiry(['prospect_name' => 'Sample Waiting']);
        $this->inquiry(['prospect_name' => 'Sample Referred', 'status' => 'Referred']);

        $this->actingAs($this->caretaker)->get('/inquiries')->assertOk()
            ->assertSee('Sample Waiting')->assertSee('Sample Referred');

        $this->actingAs($this->caretaker)->get('/inquiries?status=Referred')->assertOk()
            ->assertSee('Sample Referred')->assertDontSee('Sample Waiting');

        $this->actingAs($this->caretaker)->get('/inquiries?status=Bogus')->assertSessionHasErrors('status');
    }

    public function test_guests_cannot_see_rooms_or_inquiries(): void
    {
        $this->get('/rooms')->assertRedirect('/login');
        $this->get('/inquiries')->assertRedirect('/login');
        $this->post('/inquiries', $this->validInquiry())->assertRedirect('/login');
    }

    public function test_philippine_contact_number_validation(): void
    {
        // Valid 09 format
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'contact_no' => '09181234567',
        ]))->assertSessionHasNoErrors();

        // Valid +639 format
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'prospect_name' => 'Sample Prospect International',
            'contact_no' => '+639171234567',
        ]))->assertSessionHasNoErrors();

        // Invalid: landline / non-PH / malformed
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'contact_no' => '0281234567',
        ]))->assertSessionHasErrors(['contact_no' => 'The contact number must be a valid Philippine mobile number (e.g., 09XXXXXXXXX or +639XXXXXXXXX).']);

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'contact_no' => '09123',
        ]))->assertSessionHasErrors('contact_no');

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'contact_no' => '+14155552671',
        ]))->assertSessionHasErrors('contact_no');
    }

    public function test_formatted_phone_accessor_and_call_intent_link(): void
    {
        $inquiry = $this->inquiry(['contact_no' => '0917 555 0142']);
        $this->assertSame('09175550142', $inquiry->formatted_phone);
        $this->assertSame('tel:09175550142', $inquiry->telHref());

        $this->actingAs($this->caretaker)->get('/inquiries')
            ->assertOk()
            ->assertSee('href="tel:09175550142"', false);
    }

    public function test_status_pills_display_record_counts_and_waiting_badge(): void
    {
        $this->inquiry(['prospect_name' => 'Waiting 1', 'status' => 'Waiting']);
        $this->inquiry(['prospect_name' => 'Waiting 2', 'status' => 'Waiting']);
        $this->inquiry(['prospect_name' => 'Rented 1', 'status' => 'Rented', 'room_id' => $this->room('201', 'Basic')->room_id]);
        $this->inquiry(['prospect_name' => 'Referred 1', 'status' => 'Referred']);
        $this->inquiry(['prospect_name' => 'Declined 1', 'status' => 'Declined']);

        $res = $this->actingAs($this->caretaker)->get('/inquiries')->assertOk();
        $res->assertSee('2 waiting');
        $res->assertSee('All <span class="chip-count">(5)</span>', false);
        $res->assertSee('Waiting <span class="chip-count">(2)</span>', false);
        $res->assertSee('Rented <span class="chip-count">(1)</span>', false);
        $res->assertSee('Referred <span class="chip-count">(1)</span>', false);
        $res->assertSee('Declined <span class="chip-count">(1)</span>', false);
    }

    public function test_referred_and_declined_are_tracked_and_stored_independently(): void
    {
        $referred = $this->inquiry(['prospect_name' => 'Student Rule Mismatch', 'status' => 'Referred', 'remarks' => 'Student curfew issue']);
        $declined = $this->inquiry(['prospect_name' => 'Pricing Objection', 'status' => 'Declined', 'remarks' => 'Budget too low']);

        $this->assertDatabaseHas('tbl_inquiry', [
            'inquiry_id' => $referred->inquiry_id,
            'status' => 'Referred',
            'remarks' => 'Student curfew issue',
        ]);
        $this->assertDatabaseHas('tbl_inquiry', [
            'inquiry_id' => $declined->inquiry_id,
            'status' => 'Declined',
            'remarks' => 'Budget too low',
        ]);

        $this->actingAs($this->caretaker)->get('/inquiries?status=Referred')
            ->assertOk()
            ->assertSee('Student Rule Mismatch')
            ->assertDontSee('Pricing Objection');

        $this->actingAs($this->caretaker)->get('/inquiries?status=Declined')
            ->assertOk()
            ->assertSee('Pricing Objection')
            ->assertDontSee('Student Rule Mismatch');
    }

    public function test_quick_outcome_status_update_endpoint(): void
    {
        $inquiry = $this->inquiry(['status' => 'Waiting']);

        $this->actingAs($this->caretaker)->patch("/inquiries/{$inquiry->inquiry_id}/status", [
            'status' => 'Referred',
            'remarks' => 'Needs 4 parking spots, referred elsewhere',
        ])->assertRedirect('/inquiries')->assertSessionHas('status');

        $this->assertSame('Referred', $inquiry->refresh()->status);
        $this->assertSame('Needs 4 parking spots, referred elsewhere', $inquiry->remarks);

        $this->assertDatabaseHas('tbl_audit_log', [
            'action' => 'Inquiry status updated',
            'table_name' => 'tbl_inquiry',
            'record_id' => $inquiry->inquiry_id,
        ]);
    }

    public function test_encode_moa_button_visibility_rules(): void
    {
        $room = $this->room('204', 'Basic', 'Vacant');
        $waitingInquiry = $this->inquiry([
            'status' => 'Waiting',
            'preferred_type' => 'Basic',
            'room_id' => $room->room_id,
        ]);
        $referredInquiry = $this->inquiry([
            'prospect_name' => 'Prospect Referred',
            'status' => 'Referred',
        ]);

        $manager = User::factory()->role('Manager')->create();
        $owner = User::factory()->role('Owner')->create();

        // Manager and Owner see Encode MOA for Waiting inquiry with vacant room
        $this->actingAs($manager)->get('/inquiries')->assertOk()
            ->assertSee('Encode MOA')
            ->assertSee(route('leases.create', ['inquiry_id' => $waitingInquiry->inquiry_id, 'room_id' => $room->room_id]));

        $this->actingAs($owner)->get('/inquiries')->assertOk()
            ->assertSee('Encode MOA');

        // Caretaker does NOT see Encode MOA
        $this->actingAs($this->caretaker)->get('/inquiries')->assertOk()
            ->assertDontSee('Encode MOA');

        // If room is occupied, Encode MOA is not rendered
        $room->update(['status' => 'Occupied']);
        $this->actingAs($manager)->get('/inquiries')->assertOk()
            ->assertDontSee('Encode MOA');
    }

    public function test_caretaker_can_log_inquiry_and_update_outcome_but_cannot_encode_moa(): void
    {
        // Caretaker can log
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'prospect_name' => 'Caretaker Walk-in Lead',
        ]))->assertRedirect('/inquiries');

        $inquiry = Inquiry::where('prospect_name', 'Caretaker Walk-in Lead')->firstOrFail();

        // Caretaker can update outcome
        $this->actingAs($this->caretaker)->patch("/inquiries/{$inquiry->inquiry_id}/status", [
            'status' => 'Declined',
            'remarks' => 'Looking for pet-friendly unit',
        ])->assertRedirect('/inquiries');

        $this->assertSame('Declined', $inquiry->refresh()->status);

        // Caretaker cannot access lease encoding
        $this->actingAs($this->caretaker)->get(route('leases.create', ['inquiry_id' => $inquiry->inquiry_id]))
            ->assertForbidden();
    }

    public function test_intake_form_has_no_room_selector_and_waiting_view_hides_assigned_room_column(): void
    {
        $room = $this->room('204', 'Basic');
        $this->inquiry(['prospect_name' => 'Waiting Lead', 'status' => 'Waiting']);
        $this->inquiry(['prospect_name' => 'Rented Tenant', 'status' => 'Rented', 'room_id' => $room->room_id]);

        $response = $this->actingAs($this->caretaker)->get('/inquiries')->assertOk();

        // Intake form does NOT have Room Shown or Rented selector
        $response->assertDontSee('Room shown or rented');

        // Waiting filter view completely hides Assigned Room column
        $waitingView = $this->actingAs($this->caretaker)->get('/inquiries?status=Waiting')->assertOk();
        $waitingView->assertDontSee('<th>Assigned Room</th>', false);
        $waitingView->assertDontSee('Leased room:');

        // All / Rented view displays Assigned Room and the leased room number for Rented inquiries
        $rentedView = $this->actingAs($this->caretaker)->get('/inquiries?status=Rented')->assertOk();
        $rentedView->assertSee('<th>Assigned Room</th>', false);
        $rentedView->assertSee('204');
        $rentedView->assertSee('Leased room:');
    }
}
