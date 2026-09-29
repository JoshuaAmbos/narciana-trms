<?php

namespace Tests\Unit;

use App\Services\BillingService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Hand-computed examples (decision 13: rent ahead, utilities behind).
 *
 * Amounts:
 *   Room 209 Aircon: rent 3,600.00 + water 2 × 200.00 = 400.00 + electricity 108.00 kWh × 15.00 = 1,620.00  -> 5,620.00
 *   Room 204 Basic:  rent 3,500.00 + water 2 × 200.00 = 400.00 + appliances 2 × 200.00 = 400.00            -> 4,300.00
 *
 * Dates:
 *   due day 31 in Feb 2026 -> 28 Feb; in Feb 2028 (leap year) -> 29 Feb; in Apr 2026 -> 30 Apr
 *   move-in 25 Jun 2026, due day 25 -> first due 25 Jul 2026, first period 25 Jun – 24 Jul
 *   next bill due 25 Aug 2026 -> period 25 Jul – 24 Aug
 *   due day 31: bill due 31 Mar 2026 -> period 28 Feb – 30 Mar (previous due date was clamped to 28 Feb)
 */
class BillingServiceRulesTest extends TestCase
{
    private BillingService $billing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billing = new BillingService;
    }

    private function d(string $date): Carbon
    {
        return Carbon::parse($date);
    }

    // ---------- Amounts ----------

    public function test_aircon_room_209(): void
    {
        $a = $this->billing->computeAmounts(3600, 200, 2, true, 108.00, 15, 200, 3);

        $this->assertSame(3600.0, $a['rent_amount']);
        $this->assertSame(400.0, $a['water_amount']);
        $this->assertSame(1620.0, $a['electricity_amount']);
        $this->assertSame(0.0, $a['appliance_amount'], 'Metered room: no appliance charge even if appliances exist');
        $this->assertSame(0.0, $a['penalty_amount']);
        $this->assertSame(0.0, $a['other_charges']);
        $this->assertSame(5620.0, $a['total_amount']);
    }

    public function test_basic_room_204(): void
    {
        $a = $this->billing->computeAmounts(3500, 200, 2, false, 999.0, 15, 200, 2);

        $this->assertSame(0.0, $a['electricity_amount'], 'Room without a meter: electricity is included in rent');
        $this->assertSame(400.0, $a['appliance_amount']);
        $this->assertSame(4300.0, $a['total_amount']);
    }

    public function test_centavos_are_rounded(): void
    {
        // 12.34 kWh × 15.50 = 191.27; 1 occupant × 199.99
        $a = $this->billing->computeAmounts(3000, 199.99, 1, true, 12.34, 15.50, 200, 0);

        $this->assertSame(191.27, $a['electricity_amount']);
        $this->assertSame(3391.26, $a['total_amount']);
    }

    public function test_no_occupants_and_no_appliances(): void
    {
        $a = $this->billing->computeAmounts(3000, 200, 0, false, 0, 15, 200, 0);

        $this->assertSame(3000.0, $a['total_amount']);
    }

    // ---------- Dates ----------

    public function test_due_day_is_clamped_to_the_last_day_of_the_month(): void
    {
        $this->assertSame('2026-02-28', $this->billing->dueDateIn(31, $this->d('2026-02-01'))->toDateString());
        $this->assertSame('2028-02-29', $this->billing->dueDateIn(31, $this->d('2028-02-10'))->toDateString());
        $this->assertSame('2026-04-30', $this->billing->dueDateIn(31, $this->d('2026-04-01'))->toDateString());
        $this->assertSame('2026-09-15', $this->billing->dueDateIn(15, $this->d('2026-09-30'))->toDateString());
    }

    public function test_first_due_date_is_the_due_day_of_the_next_month(): void
    {
        $this->assertSame('2026-07-25', $this->billing->firstDueDate($this->d('2026-06-25'), 25)->toDateString());
        $this->assertSame('2026-02-28', $this->billing->firstDueDate($this->d('2026-01-31'), 31)->toDateString());
        $this->assertSame('2026-07-25', $this->billing->firstDueDate($this->d('2026-06-20'), 25)->toDateString());
    }

    public function test_first_period_starts_at_move_in(): void
    {
        [$start, $end] = $this->billing->periodFor($this->d('2026-06-25'), 25, $this->d('2026-07-25'));

        $this->assertSame('2026-06-25', $start->toDateString());
        $this->assertSame('2026-07-24', $end->toDateString());
    }

    public function test_later_period_runs_from_the_previous_due_date(): void
    {
        [$start, $end] = $this->billing->periodFor($this->d('2026-06-25'), 25, $this->d('2026-08-25'));

        $this->assertSame('2026-07-25', $start->toDateString());
        $this->assertSame('2026-08-24', $end->toDateString());
    }

    public function test_period_with_due_day_31_after_february(): void
    {
        [$start, $end] = $this->billing->periodFor($this->d('2026-01-31'), 31, $this->d('2026-03-31'));

        $this->assertSame('2026-02-28', $start->toDateString());
        $this->assertSame('2026-03-30', $end->toDateString());
    }
}
