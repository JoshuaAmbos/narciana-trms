<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Lease;
use App\Models\Moveout;
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

    public function test_open_concerns_vacant_rooms_and_refunds_due_with_demo_data(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);
        $owner = User::where('username', 'owner')->first();

        // Pretend the settled refund of room 215 is approved but not handed over yet, and late.
        $moveout = Moveout::whereHas('lease.room', fn ($q) => $q->where('room_no', '215'))->firstOrFail();
        $moveout->update(['status' => 'Refunded', 'refund_due_date' => today()->subDay()]);

        $d = app(DashboardService::class)->forStaff();
        $this->assertSame(6, $d['counts']['open_concerns']);               // 4 open + 2 in progress
        $this->assertSame(1, $d['counts']['urgent_concerns']);
        $this->assertSame('Urgent', $d['concerns']->first()->priority);   // urgent first
        $this->assertCount(2, $d['refunds']);                             // 215 (made late above) and 216

        // Tenants moving out: every move-out not settled yet (206, 214, 216, 217; 215 was set back to Refunded above).
        $this->assertEqualsCanonicalizing(['206', '214', '215', '216', '217'], $d['movingOut']->map(fn ($m) => $m->lease->room->room_no)->all());

        $this->actingAs($owner)->get('/')->assertOk()
            ->assertSee('Open concerns (1 urgent)')
            ->assertSee('Moving out')
            ->assertSee('Refunds due')
            ->assertSee('₱'.number_format((float) $moveout->refund_amount, 2))
            ->assertSee('Past due');
    }

    public function test_caretaker_sees_only_concerns_and_rooms(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);
        $caretaker = User::where('username', 'caretaker')->first();
        $contact = Lease::with('tenant')->first()->tenant->contact_no;

        $this->actingAs($caretaker)->get('/')->assertOk()
            ->assertViewMissing('toCollect')->assertViewMissing('refunds')->assertViewMissing('unbilled')
            ->assertSee('Open concerns')->assertSee('Vacant rooms')
            ->assertDontSee('Due and overdue')->assertDontSee('Overdue tenants')->assertDontSee('Refunds due')
            ->assertDontSee('₱')
            ->assertDontSee($contact);
    }

    public function test_dashboard_still_works_with_no_data(): void
    {
        $this->actingAs($this->owner)->get('/')->assertOk()
            ->assertSee('No bills due in the next 7 days and nothing overdue.')
            ->assertSee('No open concerns.')
            ->assertSee('No approved refunds waiting to be handed over.');
    }
}
