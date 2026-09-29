<?php

namespace Tests\Unit;

use App\Models\Visitor;
use App\Services\VisitorService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * MOA two-day rule, hand-worked (decision 12):
 *   Sep 1 -> Sep 1 = 0 days   OK
 *   Sep 1 -> Sep 3 = 2 days   OK
 *   Sep 1 -> Sep 4 = 3 days   flagged
 *   still here: arrived Sep 26, today Sep 29 = 3 days flagged; arrived Sep 27 = 2 days OK
 *   across a month end: Aug 30 -> Sep 2 = 3 days flagged
 */
class VisitorServiceTest extends TestCase
{
    private function visitor(string $arrival, ?string $departure = null): Visitor
    {
        return new Visitor(['arrival_date' => $arrival, 'departure_date' => $departure]);
    }

    public function test_hand_worked_days(): void
    {
        $rule = new VisitorService;

        $this->assertSame(0, $rule->stayDays($this->visitor('2026-09-01', '2026-09-01')));
        $this->assertSame(2, $rule->stayDays($this->visitor('2026-09-01', '2026-09-03')));
        $this->assertSame(3, $rule->stayDays($this->visitor('2026-09-01', '2026-09-04')));
        $this->assertSame(3, $rule->stayDays($this->visitor('2026-08-30', '2026-09-02')));
    }

    public function test_flag_is_more_than_two_days(): void
    {
        $rule = new VisitorService;

        $this->assertFalse($rule->isOverStay($this->visitor('2026-09-01', '2026-09-03')));
        $this->assertTrue($rule->isOverStay($this->visitor('2026-09-01', '2026-09-04')));
    }

    public function test_visitor_still_there_counts_up_to_today(): void
    {
        $rule = new VisitorService;
        $today = Carbon::parse('2026-09-29');

        $this->assertSame(3, $rule->stayDays($this->visitor('2026-09-26'), $today));
        $this->assertTrue($rule->isOverStay($this->visitor('2026-09-26'), $today));
        $this->assertFalse($rule->isOverStay($this->visitor('2026-09-27'), $today));
    }
}
