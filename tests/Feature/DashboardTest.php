<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Lease;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DashboardService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/**
 * Hand-computed examples. Leases: Basic rooms, rent 3,500.00, 1 occupant, move-in 25 Jun 2026, due day 25.
 * Each bill = 3,500.00 rent + 200.00 water = 3,700.00. Grace 3 days; penalty 1% of rent per day after the grace period.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private User $owner;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role('Owner')->create();
        $this->settings($this->owner);
        $this->manager = User::factory()->role('Manager')->create();
    }

    private function billOn(Lease $lease, string $date): Bill
    {
        $this->travelTo(Carbon::parse($date));
        $billing = app(BillingService::class);

        return $billing->generate($lease, null, $billing->nextDueDate($lease)->toDateString(), $this->manager);
    }

    private function dashboard(string $today): array
    {
        $this->travelTo(Carbon::parse($today));

        return app(DashboardService::class)->forStaff(today());
    }

    public function test_bills_due_in_the_next_7_days_window_edges(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->billOn($lease, '2026-07-18'); // due 25 Jul

        $this->assertSame(0, $this->dashboard('2026-07-17')['counts']['due_soon']); // 25 Jul is 8 days away
        $this->assertSame(1, $this->dashboard('2026-07-18')['counts']['due_soon']); // today + 7
        $this->assertSame(1, $this->dashboard('2026-07-25')['counts']['due_soon']); // due today
        $this->assertSame(1, $this->dashboard('2026-07-28')['counts']['due_soon']); // last day of grace: not late yet

        $d = $this->dashboard('2026-07-29');                                        // 1 day late
        $this->assertSame(0, $d['counts']['due_soon']);
        $this->assertSame(1, $d['counts']['overdue_tenants']);
    }

    public function test_overdue_tenants_counts_leases_not_bills_and_shows_penalty_so_far(): void
    {
        $a = $this->makeLease($this->makeRoom('204'), $this->manager, null, 'Sample Tenant A');
        $b = $this->makeLease($this->makeRoom('205'), $this->manager, null, 'Sample Tenant B');
        $this->billOn($a, '2026-07-20');
        $this->billOn($b, '2026-07-20');
        $this->billOn($a, '2026-08-20');

        $d = $this->dashboard('2026-09-01');

        // A has 2 overdue bills, B has 1: 2 tenants, 3 bills.
        $this->assertSame(2, $d['counts']['overdue_tenants']);
        $this->assertCount(3, $d['toCollect']);
        $this->assertSame(0, $d['counts']['due_soon']);

        // Bill due 25 Jul, on 1 Sep: 1 Sep - (25 Jul + 3) = 35 days late; 3,500 x 1% x 35 = 1,225.00.
        $first = $d['toCollect']->first();
        $this->assertSame(35, $first['days_late']);
        $this->assertSame(1225.0, $first['penalty']);
        $this->assertSame(4925.0, $first['total']);
        // Not saved: the bill still holds no penalty.
        $this->assertSame('0.00', $first['bill']->fresh()->penalty_amount);

        $this->actingAs($this->manager)->get('/')->assertOk()
            ->assertSee('Overdue 35 days')->assertSee('₱4,925.00')->assertSee('incl. penalty ₱1,225.00');
    }

    public function test_leases_due_soon_without_a_bill_have_a_generate_now_link(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);

        $this->assertCount(0, $this->dashboard('2026-07-17')['unbilled']); // first bill due 25 Jul: too early

        $unbilled = $this->dashboard('2026-07-18')['unbilled'];
        $this->assertCount(1, $unbilled);
        $this->assertSame('2026-07-25', $unbilled->first()['due_date']->toDateString());
        $this->assertSame(0, $this->dashboard('2026-07-18')['counts']['due_soon']); // not generated = not counted

        $this->actingAs($this->manager)->get('/')->assertOk()
            ->assertSee('Due soon, no bill yet')
            ->assertSee(route('bills.create', ['lease' => $lease->lease_id]), false)
            ->assertSee('Generate now');

        $this->billOn($lease, '2026-07-18');
        $this->assertCount(0, $this->dashboard('2026-07-18')['unbilled']);
    }

    public function test_open_concerns_and_vacant_rooms_with_demo_data(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);
        $owner = User::where('username', 'owner')->first();

        $d = app(DashboardService::class)->forStaff();
        $this->assertSame(6, $d['counts']['open_concerns']);               // 4 open + 2 in progress
        $this->assertSame(1, $d['counts']['urgent_concerns']);
        $this->assertSame('Urgent', $d['concerns']->first()->priority);   // urgent first
        $this->assertSame(4, $d['counts']['vacant_rooms']);               // 203, 205, 208, 215

        $this->actingAs($owner)->get('/')->assertOk()
            ->assertSee('Open concerns (1 urgent)')
            ->assertSee('Vacant rooms')
            ->assertDontSee('Refunds due')
            ->assertDontSee('Move-out');
    }

    public function test_caretaker_sees_only_concerns_and_rooms(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);
        $caretaker = User::where('username', 'caretaker')->first();
        $contact = Lease::with('tenant')->first()->tenant->contact_no;

        $this->actingAs($caretaker)->get('/')->assertOk()
            ->assertViewMissing('toCollect')->assertViewMissing('unbilled')
            ->assertSee('Open concerns')->assertSee('Vacant rooms')
            ->assertDontSee('Due and overdue')->assertDontSee('Overdue tenants')->assertDontSee('Refunds due')
            ->assertDontSee('₱')
            ->assertDontSee($contact);
    }

    public function test_dashboard_still_works_with_no_data(): void
    {
        $this->actingAs($this->owner)->get('/')->assertOk()
            ->assertSee('No bills due in the next 7 days and nothing overdue.')
            ->assertSee('No open concerns.');
    }

    public function test_multi_tenant_dual_occupancy_display_on_dashboard(): void
    {
        $room = $this->makeRoom('204', 'Basic');
        $lease = $this->makeLease($room, $this->manager, 'Juan Santos', 'Maria Santos');
        $this->billOn($lease, '2026-07-20'); // due 25 Jul 2026

        $this->travelTo(Carbon::parse('2026-07-22'));

        $this->actingAs($this->manager)->get('/')
            ->assertOk()
            ->assertSee('Maria Santos & Juan Santos');
    }

    public function test_submeter_reading_needed_alert_for_aircon_room(): void
    {
        $room = $this->makeRoom('207', 'Aircon', ['is_metered' => true]);
        $lease = $this->makeLease($room, $this->manager);

        // Due 25 Jul 2026; on 18 Jul it is 7 days ahead and unbilled
        $this->travelTo(Carbon::parse('2026-07-18'));
        $d = app(DashboardService::class)->forStaff(today());

        $this->assertSame(1, $d['counts']['readings_needed']);
        $this->assertCount(1, $d['readingsNeeded']);

        $this->actingAs($this->manager)->get('/')
            ->assertOk()
            ->assertSee('Meter reading needed')
            ->assertSee('Sub-Meter Readings Needed')
            ->assertSee('1 Meter Reading Needed');
    }

    public function test_room_turnover_and_vacating_alert(): void
    {
        $room = $this->makeRoom('208', 'Basic', [
            'status' => 'Occupied',
            'expected_vacant_date' => Carbon::parse('2026-08-15'),
        ]);

        $this->travelTo(Carbon::parse('2026-08-01'));
        $d = app(DashboardService::class)->forStaff(today());

        $this->assertSame(1, $d['counts']['vacating_rooms']);
        $this->assertCount(1, $d['vacatingRooms']);

        $this->actingAs($this->manager)->get('/')
            ->assertOk()
            ->assertSee('1 Room Turnover Alert')
            ->assertSee('Vacating');
    }

    public function test_caretaker_dashboard_privacy_and_scoping(): void
    {
        $caretaker = User::factory()->role('Caretaker')->create();
        $room = $this->makeRoom('204', 'Basic');
        $lease = $this->makeLease($room, $this->manager, 'Secret Co-Tenant', 'Main Tenant');

        $this->actingAs($caretaker)->get('/')
            ->assertOk()
            ->assertDontSee('Secret Co-Tenant')
            ->assertDontSee('Aircon reading')
            ->assertDontSee('Sub-Meter')
            ->assertDontSee('Bills due')
            ->assertDontSee('Due and overdue');
    }
}
