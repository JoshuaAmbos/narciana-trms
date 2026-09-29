<?php

namespace Tests\Unit;

use App\Services\PenaltyService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Hand-computed examples (decisions 1 and 14), due 25 Sep 2026, grace 3 days (last on-time day 28 Sep):
 *   paid 20 Sep -> 0 days; 25 Sep -> 0; 28 Sep -> 0; 29 Sep -> 1; 30 Sep -> 2
 *   across a month end: due 31 Jan 2026, grace 3 (last on-time day 3 Feb), paid 5 Feb -> 2 days
 *
 *   Per day of rent: 3,500.00 × 1% × 2 days = 70.00
 *                    3,000.00 × 1.5% × 7 days = 315.00
 *                    3,333.33 × 1% × 3 days = 99.9999 -> 100.00
 *   Flat % of total: 4,000.00 × 2% = 80.00 (any number of days late), 0 when not late
 */
class PenaltyServiceTest extends TestCase
{
    private PenaltyService $penalty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->penalty = new PenaltyService;
    }

    private function late(string $due, string $paid, int $grace = 3): int
    {
        return $this->penalty->daysLate(Carbon::parse($due), Carbon::parse($paid), $grace);
    }

    public function test_days_late_are_counted_after_the_grace_period(): void
    {
        $this->assertSame(0, $this->late('2026-09-25', '2026-09-20'));
        $this->assertSame(0, $this->late('2026-09-25', '2026-09-25'));
        $this->assertSame(0, $this->late('2026-09-25', '2026-09-28'));
        $this->assertSame(1, $this->late('2026-09-25', '2026-09-29'));
        $this->assertSame(2, $this->late('2026-09-25', '2026-09-30'));
    }

    public function test_days_late_across_a_month_end(): void
    {
        $this->assertSame(2, $this->late('2026-01-31', '2026-02-05'));
    }

    public function test_zero_grace_days(): void
    {
        $this->assertSame(0, $this->late('2026-09-25', '2026-09-25', 0));
        $this->assertSame(1, $this->late('2026-09-25', '2026-09-26', 0));
    }

    public function test_per_day_of_rent(): void
    {
        $this->assertSame(70.0, $this->penalty->amount('Per day of rent', 1.00, 3500, 3700, 2));
        $this->assertSame(315.0, $this->penalty->amount('Per day of rent', 1.50, 3000, 3200, 7));
        $this->assertSame(100.0, $this->penalty->amount('Per day of rent', 1.00, 3333.33, 3533.33, 3));
        $this->assertSame(0.0, $this->penalty->amount('Per day of rent', 1.00, 3500, 3700, 0));
    }

    public function test_flat_percent_of_total(): void
    {
        $this->assertSame(80.0, $this->penalty->amount('Flat % of total', 2.00, 3500, 4000, 1));
        $this->assertSame(80.0, $this->penalty->amount('Flat % of total', 2.00, 3500, 4000, 15));
        $this->assertSame(0.0, $this->penalty->amount('Flat % of total', 2.00, 3500, 4000, 0));
    }
}
