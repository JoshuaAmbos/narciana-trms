<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Concern;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Moveout;
use App\Models\Room;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use App\Services\PenaltyService;
use App\Services\ReportService;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\RentalFixtures;
use Tests\TestCase;

/**
 * Hand-computed examples. Leases: Basic rooms, rent 3,500.00, 1 occupant, move-in 25 Jun 2026, due day 25.
 * Each bill = 3,500.00 rent + 200.00 water = 3,700.00. Grace 3 days; penalty 1% of rent (35.00) per day after it.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase, RentalFixtures;

    private const ROUTES = ['reports.index', 'reports.collections', 'reports.due', 'reports.statement',
        'reports.concerns', 'reports.inquiries', 'reports.moveouts'];

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

    private function payOn(Bill $bill, string $date, User $by, string $mode = 'Cash', ?string $ref = null): void
    {
        $this->travelTo(Carbon::parse($date));
        app(PaymentService::class)->record($bill, ['payment_date' => $date, 'mode' => $mode, 'gcash_ref_no' => $ref,
            'amount' => app(PenaltyService::class)->forBill($bill, Carbon::parse($date))['total']], $by);
    }

    private function reports(): ReportService
    {
        return app(ReportService::class);
    }

    // ---------- Access ----------

    public function test_caretaker_cannot_open_any_report(): void
    {
        $caretaker = User::factory()->role('Caretaker')->create();
        foreach (self::ROUTES as $route) {
            $this->actingAs($caretaker)->get(route($route))->assertForbidden();
        }
        $this->actingAs($caretaker)->get('/')->assertDontSee(route('reports.index'), false);
    }

    public function test_owner_and_manager_open_every_report_with_demo_data(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);
        $lease = Lease::where('status', 'Ended')->firstOrFail();

        foreach ([User::where('username', 'owner')->first(), User::where('username', 'manager')->first()] as $user) {
            foreach (self::ROUTES as $route) {
                $this->actingAs($user)->get(route($route))->assertOk()->assertSee('Narciana House');
            }
            $this->actingAs($user)->get(route('reports.statement', ['lease' => $lease->lease_id]))->assertOk()
                ->assertSee('Statement of account')->assertSee($lease->tenant->full_name);
        }
    }

    public function test_bad_filters_are_rejected(): void
    {
        $this->actingAs($this->manager)->get(route('reports.moveouts', ['from' => '2026-09-30', 'to' => '2026-09-01']))
            ->assertSessionHasErrors('to');
        $this->actingAs($this->manager)->get(route('reports.collections', ['month' => 'September']))->assertSessionHasErrors('month');
        $this->actingAs($this->manager)->get(route('reports.concerns', ['room' => 'abc']))->assertSessionHasErrors('room');
        $this->actingAs($this->manager)->get(route('reports.statement', ['lease' => 99999]))->assertSessionHasErrors('lease');
        $this->actingAs($this->manager)->get(route('reports.concerns', ['room' => 'shared']))->assertOk();
    }

    // ---------- 1. Collections ----------

    public function test_collection_summary_cash_vs_gcash_and_per_receiver(): void
    {
        $a = $this->makeLease($this->makeRoom('204'), $this->manager, null, 'Sample Tenant A');
        $b = $this->makeLease($this->makeRoom('205'), $this->manager, null, 'Sample Tenant B');
        $billA = $this->billOn($a, '2026-07-20');
        $billB = $this->billOn($b, '2026-07-20');
        $this->payOn($billA, '2026-07-24', $this->manager);                              // 3,700.00 cash, on time
        $this->payOn($billB, '2026-07-30', $this->owner, 'GCash', '1234567890123');      // 2 days late: 3,770.00

        $report = $this->reports()->collections(Carbon::parse('2026-07-01'));

        $this->assertSame(['Cash' => 3700.0, 'GCash' => 3770.0, 'All' => 7470.0], $report['totals']);
        $this->assertCount(2, $report['receivers']);
        $owner = $report['receivers']->firstWhere('user.user_id', $this->owner->user_id);
        $this->assertSame([0.0, 3770.0, 1], [$owner['Cash'], $owner['GCash'], $owner['count']]);
        $this->assertSame(0.0, $this->reports()->collections(Carbon::parse('2026-08-01'))['totals']['All']);

        $this->actingAs($this->owner)->get(route('reports.collections', ['month' => '2026-07']))->assertOk()
            ->assertSee('July 2026')->assertSee('Test Owner (Owner)')->assertSee('Test Manager (Manager)')
            ->assertSee('₱7,470.00')->assertSee('ACK-2026-0002');
    }

    // ---------- 2. Due and overdue ----------

    public function test_due_and_overdue_with_penalty_so_far(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $this->billOn($lease, '2026-07-20');   // due 25 Jul
        $this->billOn($lease, '2026-08-20');   // due 25 Aug
        $this->travelTo(Carbon::parse('2026-09-01'));

        $report = $this->reports()->dueAndOverdue();

        // 25 Jul: 35 days late -> 1,225.00; 25 Aug: 1 Sep - 28 Aug = 4 days -> 140.00.
        $this->assertSame([1225.0, 140.0], $report['rows']->pluck('penalty')->all());
        $this->assertSame(1, $report['overdue_tenants']);
        $this->assertSame(1365.0, $report['penalty_total']);
        $this->assertSame(8765.0, $report['total']);           // 3,700 + 3,700 + 1,365
        $this->assertSame(8765.0, $report['overdue_total']);
        $this->assertSame(0.0, $report['due_total']);

        $this->actingAs($this->manager)->get(route('reports.due'))->assertOk()
            ->assertSee('Overdue 35 days')->assertSee('₱8,765.00')->assertSee('0918 555 0123');
    }

    // ---------- 3. Statement of account ----------

    public function test_statement_running_balance_with_late_payment_and_unpaid_bill(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $july = $this->billOn($lease, '2026-07-20');
        $this->payOn($july, '2026-07-30', $this->manager);     // 2 days late: penalty 70.00, paid 3,770.00
        $this->billOn($lease, '2026-08-20');                   // unpaid
        $this->travelTo(Carbon::parse('2026-09-01'));

        $s = $this->reports()->statement($lease);

        $this->assertSame(['bill', 'penalty', 'payment', 'bill'], $s['entries']->pluck('type')->all());
        $this->assertSame([3700.0, 3770.0, 0.0, 3700.0], $s['entries']->pluck('balance')->all());
        $this->assertSame(7470.0, $s['charges']);              // 3,700 + 70 + 3,700
        $this->assertSame(3770.0, $s['credits']);
        $this->assertSame(3700.0, $s['closing']);
        $this->assertSame(140.0, $s['penalty_so_far']);        // August bill, 4 days late on 1 Sep
        $this->assertSame(3840.0, $s['due_today']);

        // From 28 Jul: the July bill is the opening balance.
        $s = $this->reports()->statement($lease, Carbon::parse('2026-07-28'));
        $this->assertSame(3700.0, $s['opening']);
        $this->assertSame(['penalty', 'payment', 'bill'], $s['entries']->pluck('type')->all());
        $this->assertSame(3700.0, $s['closing']);

        $this->actingAs($this->manager)->get(route('reports.statement', ['lease' => $lease->lease_id]))->assertOk()
            ->assertSee('Late penalty')->assertSee('ACK-2026-0001')->assertSee('Amount due if paid today')->assertSee('₱3,840.00');
    }

    public function test_statement_bill_paid_before_its_due_date_never_shows_a_negative_balance(): void
    {
        $lease = $this->makeLease($this->makeRoom('204'), $this->manager);
        $july = $this->billOn($lease, '2026-07-20');
        $this->payOn($july, '2026-07-24', $this->manager);     // a day early

        $s = $this->reports()->statement($lease);

        $this->assertSame('2026-07-24', $s['entries']->first()['date']->toDateString());
        $this->assertSame([3700.0, 0.0], $s['entries']->pluck('balance')->all());
    }

    // ---------- 4. Concerns ----------

    public function test_concern_history_per_room_flags_repeated_issues(): void
    {
        $room = $this->makeRoom('206');
        $other = $this->makeRoom('207');
        $concern = fn (?Room $r, string $category, string $at, ?float $cost = null, bool $charged = false) => Concern::create([
            'room_id' => $r?->room_id, 'lease_id' => null, 'reported_at' => $at, 'channel' => 'Verbal', 'category' => $category,
            'priority' => 'Routine', 'description' => "Sample {$category} concern", 'assigned_to' => $this->manager->user_id,
            'status' => $cost === null ? 'Open' : 'Resolved', 'resolution' => $cost === null ? null : 'Fixed',
            'cost' => $cost, 'charged_to_tenant' => $charged, 'resolved_at' => $cost === null ? null : $at,
        ]);
        $concern($room, 'Professional repair', '2026-03-14 10:00:00', 850);
        $concern($room, 'Professional repair', '2026-09-28 21:40:00');
        $concern($room, 'Maintenance', '2026-06-02 08:00:00', 150, true);
        $concern($other, 'Maintenance', '2026-06-02 08:00:00');
        $concern(null, 'Shared area', '2026-09-27 07:15:00');

        $r = $this->reports()->concernHistory($room->room_id, null, null);
        $this->assertSame(3, $r['count']);
        $this->assertSame(1, $r['open']);
        $this->assertSame(1000.0, $r['cost']);
        $this->assertSame(150.0, $r['charged']);
        $this->assertSame(['Professional repair' => 2], $r['groups']->first()['repeated']->all());

        $this->assertSame(['Room 206', 'Room 207', 'Shared area'], $this->reports()->concernHistory(null, null, null)['groups']->pluck('label')->all());
        $this->assertSame(1, $this->reports()->concernHistory('shared', null, null)['count']);
        $this->assertSame(2, $this->reports()->concernHistory($room->room_id, Carbon::parse('2026-06-01'), null)['count']);

        $this->actingAs($this->manager)->get(route('reports.concerns', ['room' => $room->room_id]))->assertOk()
            ->assertSee('Repeated issue')->assertSee('Professional repair reported 2 times');
    }

    // ---------- 5. Inquiries ----------

    public function test_inquiry_and_room_demand_summary(): void
    {
        $basic = $this->makeRoom('203');
        $this->makeRoom('208', 'Aircon');
        $inquiry = fn (string $type, string $status, string $date, string $channel = 'Walk-in') => Inquiry::create([
            'prospect_name' => 'Sample Prospect', 'contact_no' => '0917 555 0100', 'preferred_type' => $type, 'no_of_occupants' => 1,
            'channel' => $channel, 'via_representative' => false, 'inquiry_date' => $date, 'status' => $status,
            'room_id' => $status === 'Rented' ? $basic->room_id : null, 'user_id' => $this->manager->user_id,
        ]);
        $inquiry('Aircon', 'Waiting', '2026-09-03');
        $inquiry('Aircon', 'Referred', '2026-09-10', 'Call');
        $inquiry('Aircon', 'Referred', '2026-09-12', 'Call');
        $inquiry('Basic', 'Rented', '2026-09-15');
        $inquiry('Any', 'Declined', '2026-09-20');
        $inquiry('Basic', 'Waiting', '2026-08-31');             // outside September

        $r = $this->reports()->inquiryDemand(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertSame(5, $r['total']);
        $this->assertSame(2, $r['referred']);
        $this->assertSame(['Aircon' => 3, 'Basic' => 1, 'Any' => 1], $r['ranked']->all());
        $this->assertSame(['Waiting' => 1, 'Rented' => 0, 'Referred' => 2, 'Declined' => 0], $r['byType']['Aircon']['statuses']);
        $this->assertSame(['Walk-in' => 3, 'Call' => 2], $r['channels']->all());
        // Today: 2 waiting (one from August); 1 Aircon room, vacant.
        $this->assertSame(['waiting' => 1, 'rooms' => 1, 'vacant' => 1, 'vacating' => 0], $r['now']['Aircon']);
        $this->assertSame(1, $r['now']['Basic']['waiting']);

        $this->actingAs($this->manager)->get(route('reports.inquiries', ['from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk()
            ->assertSee('Most asked for')->assertSee('Referred elsewhere');
    }

    // ---------- 6. Move-outs ----------

    public function test_moveout_report_lists_settlements_with_totals(): void
    {
        $this->seed();
        $this->seed(DemoSeeder::class);
        $all = Moveout::count();

        $r = $this->reports()->moveouts(today()->subYear(), today()->addYear());

        $this->assertCount($all, $r['rows']);
        $this->assertSame(round($r['rows']->sum('figures.refund'), 2), $r['refund']);
        $settled = $r['rows']->first(fn ($row) => $row['moveout']->status === 'Settled');
        $this->assertSame((float) $settled['moveout']->refund_amount, $settled['figures']['refund']);   // approved figures

        $this->actingAs(User::where('username', 'owner')->first())->get(route('reports.moveouts'))->assertOk()
            ->assertSee('Refundable deposit')->assertSee('Settled');
    }
}
