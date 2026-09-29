<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Room;
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
