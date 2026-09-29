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
                ->assertRedirect('/rooms')->assertSessionHas('status');

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
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry(['status' => 'Rented']))
            ->assertSessionHasErrors(['room_id' => 'Choose the room that was rented.']);

        $room = $this->room('203', 'Basic');
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry(['status' => 'Rented', 'room_id' => $room->room_id]))
            ->assertSessionHasNoErrors();
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

    public function test_three_occupants_can_be_recorded_for_the_demand_report(): void
    {
        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry(['no_of_occupants' => '3']))
            ->assertSessionHasNoErrors();
    }

    // ---------- Bug 1: occupants must fit the room when Rented ----------

    public function test_rented_is_blocked_when_occupants_exceed_the_room_maximum(): void
    {
        $room = $this->room('3', 'Aircon', 'Vacant', 2);

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'no_of_occupants' => '3', 'status' => 'Rented', 'room_id' => $room->room_id,
        ]))->assertSessionHasErrors(['no_of_occupants' => 'Room 3 allows at most 2 occupants; this inquiry has 3.']);

        $inquiry = $this->inquiry(['no_of_occupants' => 3, 'preferred_type' => 'Aircon']);
        $this->actingAs($this->caretaker)->put("/inquiries/{$inquiry->inquiry_id}", $this->validInquiry([
            'no_of_occupants' => '3', 'status' => 'Rented', 'room_id' => $room->room_id,
        ]))->assertSessionHasErrors('no_of_occupants');

        $this->assertSame('Waiting', $inquiry->refresh()->status);
        $this->assertSame(1, Inquiry::count());
    }

    public function test_one_occupant_room_blocks_two_occupants(): void
    {
        $room = $this->room('9', 'Basic', 'Vacant', 1);

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'preferred_type' => 'Basic', 'no_of_occupants' => '2', 'status' => 'Rented', 'room_id' => $room->room_id,
        ]))->assertSessionHasErrors(['no_of_occupants' => 'Room 9 allows at most 1 occupant; this inquiry has 2.']);
    }

    public function test_three_occupants_can_still_be_waiting_or_declined_with_a_room_shown(): void
    {
        $room = $this->room('3', 'Aircon');

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'no_of_occupants' => '3', 'status' => 'Declined', 'room_id' => $room->room_id,
        ]))->assertSessionHasNoErrors();
    }

    // ---------- Bug 2: one pending Rented inquiry per room; only Vacant or Vacating rooms ----------

    public function test_a_room_held_by_another_rented_inquiry_cannot_be_rented_again(): void
    {
        $room = $this->room('3', 'Aircon');
        $this->inquiry(['prospect_name' => 'Sample First', 'status' => 'Rented', 'room_id' => $room->room_id,
            'inquiry_date' => '2026-09-20']);

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
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

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'status' => 'Rented', 'room_id' => $room->room_id,
        ]))->assertSessionHasNoErrors();
    }

    public function test_only_vacant_or_vacating_rooms_can_be_rented(): void
    {
        $occupied = $this->room('7', 'Aircon', 'Occupied');
        $vacating = $this->room('8', 'Aircon', 'Vacating');

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'status' => 'Rented', 'room_id' => $occupied->room_id,
        ]))->assertSessionHasErrors(['room_id' => 'Room 7 is Occupied. Only Vacant or Vacating rooms can be marked as rented.']);

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'status' => 'Rented', 'room_id' => $vacating->room_id,
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

        $this->actingAs($this->caretaker)->get('/rooms')->assertSee('rented to Sample First, MOA pending');
    }

    public function test_type_mismatch_is_a_warning_not_an_error(): void
    {
        $basic = $this->room('1', 'Basic');

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'preferred_type' => 'Aircon', 'status' => 'Rented', 'room_id' => $basic->room_id,
        ]))->assertSessionHasNoErrors()
            ->assertSessionHas('warning', 'Note: Room 1 is a Basic room, but the prospect asked for Aircon.');

        $this->assertSame(1, Inquiry::where('status', 'Rented')->count());
        $this->followRedirects($this->actingAs($this->caretaker)->get('/rooms'))->assertOk();
    }

    public function test_no_warning_when_type_matches_or_any(): void
    {
        $basic = $this->room('1', 'Basic');
        $other = $this->room('2', 'Basic');

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
            'preferred_type' => 'Basic', 'status' => 'Rented', 'room_id' => $basic->room_id,
        ]))->assertSessionMissing('warning');

        $this->actingAs($this->caretaker)->post('/inquiries', $this->validInquiry([
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

    public function test_waiting_list_is_oldest_first_and_shows_fitting_rooms(): void
    {
        $this->room('201', 'Basic');
        $this->room('208', 'Aircon', 'Vacating');
        $this->room('210', 'Aircon', 'Occupied');
        $this->inquiry(['prospect_name' => 'Sample Newer', 'preferred_type' => 'Aircon', 'inquiry_date' => today()->subDays(1)->toDateString()]);
        $this->inquiry(['prospect_name' => 'Sample Older', 'preferred_type' => 'Basic', 'inquiry_date' => today()->subDays(5)->toDateString()]);
        $this->inquiry(['prospect_name' => 'Sample Declined', 'status' => 'Declined']);

        $this->actingAs($this->caretaker)->get('/rooms')
            ->assertOk()
            ->assertSeeInOrder(['Prospects waiting', 'Sample Older', '201', 'Sample Newer', '208'])
            ->assertDontSee('Sample Declined')
            ->assertSee('tel:09170000000', false);
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
}
