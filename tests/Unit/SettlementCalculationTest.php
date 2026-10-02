<?php

namespace Tests\Unit;

use App\Models\Lease;
use App\Services\BillingService;
use App\Services\PenaltyService;
use App\Services\SettlementService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Hand-worked settlements (decision 16). Refundable = damage + bills + keys (maintenance never refunded).
 *
 *  Refund (Room 210): deposit 5,000.00 = maintenance 1,200.00 + damage 1,800.00 + bills 1,500.00 + keys 500.00
 *    refundable 3,800.00 − unpaid 850.00 − damage 900.00 = 2,050.00  -> refund 2,050.00
 *  Excess: refundable 1,000 + 1,200 + 300 = 2,500.00 − unpaid 3,700.00 − damage 600.00 = −1,800.00 -> excess 1,800.00
 *  Early departure: same as the refund example but early -> refund 0.00 (deposit forfeited), excess 0.00
 *  Early departure with excess: −1,800.00 -> refund 0.00, excess 1,800.00 (still owed)
 *  Exactly covered: 2,500.00 − 2,000.00 − 500.00 = 0.00 -> refund 0.00, excess 0.00
 */
class SettlementCalculationTest extends TestCase
{
    private SettlementService $settlement;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settlement = new SettlementService(new BillingService, new PenaltyService);
    }

    public function test_refund_room_210(): void
    {
        $r = $this->settlement->calculate(1800, 1500, 500, 850, 900, false);

        $this->assertSame(3800.0, $r['refundable']);
        $this->assertSame(2050.0, $r['balance']);
        $this->assertSame(2050.0, $r['refund']);
        $this->assertSame(0.0, $r['excess']);
    }

    public function test_costs_above_the_deposit_become_an_excess(): void
    {
        $r = $this->settlement->calculate(1000, 1200, 300, 3700, 600, false);

        $this->assertSame(2500.0, $r['refundable']);
        $this->assertSame(-1800.0, $r['balance']);
        $this->assertSame(0.0, $r['refund']);
        $this->assertSame(1800.0, $r['excess']);
    }

    public function test_early_departure_forfeits_the_refund(): void
    {
        $r = $this->settlement->calculate(1800, 1500, 500, 850, 900, true);

        $this->assertSame(0.0, $r['refund']);
        $this->assertSame(0.0, $r['excess']);
    }

    public function test_early_departure_still_owes_the_excess(): void
    {
        $r = $this->settlement->calculate(1000, 1200, 300, 3700, 600, true);

        $this->assertSame(0.0, $r['refund']);
        $this->assertSame(1800.0, $r['excess']);
    }

    public function test_deposit_exactly_covers_the_costs(): void
    {
        $r = $this->settlement->calculate(1000, 1200, 300, 2000, 500, false);

        $this->assertSame(0.0, $r['refund']);
        $this->assertSame(0.0, $r['excess']);
    }

    public function test_early_means_before_move_in_plus_minimum_stay(): void
    {
        // Move-in 25 Jun 2026, minimum stay 4 months -> may leave from 25 Oct 2026.
        $lease = new Lease(['move_in_date' => '2026-06-25', 'min_stay_months' => 4]);

        $this->assertTrue($this->settlement->isEarly($lease, Carbon::parse('2026-10-24')));
        $this->assertFalse($this->settlement->isEarly($lease, Carbon::parse('2026-10-25')));
    }

    public function test_one_month_notice_warning(): void
    {
        $this->assertNull($this->settlement->noticeWarning(Carbon::parse('2026-09-29'), Carbon::parse('2026-10-29')));
        $this->assertSame(
            "Note: the MOA asks for one month's notice; this notice is only 12 days.",
            $this->settlement->noticeWarning(Carbon::parse('2026-09-29'), Carbon::parse('2026-10-11')),
        );
    }

    public function test_refund_is_never_negative_a_shortfall_becomes_excess(): void
    {
        $service = $this->settlement;
        // Refundable 2,500.00; unpaid 3,900.00 + damage 600.00 = 4,500.00 -> balance −2,000.00.
        $r = $service->calculate(1500, 700, 300, 3900, 600, false);
        $this->assertSame(0.0, $r['refund']);
        $this->assertSame(2000.0, $r['excess']);

        foreach ([[0, 0, 0, 0, 0], [1500, 700, 300, 0, 0], [1500, 700, 300, 2500, 0], [100, 0, 0, 99999, 5000]] as [$d, $b, $k, $u, $dmg]) {
            foreach ([false, true] as $early) {
                $r = $service->calculate($d, $b, $k, $u, $dmg, $early);
                $this->assertGreaterThanOrEqual(0, $r['refund']);
                $this->assertGreaterThanOrEqual(0, $r['excess']);
                $this->assertFalse($r['refund'] > 0 && $r['excess'] > 0);
            }
        }
    }
}
