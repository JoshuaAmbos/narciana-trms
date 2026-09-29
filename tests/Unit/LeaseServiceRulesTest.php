<?php

namespace Tests\Unit;

use App\Services\LeaseService;
use Tests\TestCase;

/**
 * Hand-worked examples:
 *   Rent ₱3,500.00, deposits 1,000 + 1,500 + 700 + 300 = ₱3,500.00  -> no warning
 *   Rent ₱3,500.00, deposits 1,000 + 1,500 + 700 + 200 = ₱3,400.00  -> warning
 *   Rent ₱3,333.33, deposits 1,000 + 1,000 + 1,000 + 333.33 = ₱3,333.33 -> no warning (centavos add up exactly)
 *   Move-in 2026-06-25 -> due day 25; move-in 2026-01-31 -> due day 31
 */
class LeaseServiceRulesTest extends TestCase
{
    public function test_deposit_equal_to_one_month_gives_no_warning(): void
    {
        $service = new LeaseService;

        $this->assertNull($service->depositWarning(3500, 1000, 1500, 700, 300));
        $this->assertNull($service->depositWarning(3333.33, 1000, 1000, 1000, 333.33));
    }

    public function test_deposit_not_equal_to_one_month_gives_a_warning(): void
    {
        $warning = (new LeaseService)->depositWarning(3500, 1000, 1500, 700, 200);

        $this->assertSame(
            "Note: the 4 deposit parts total ₱3,400.00, but one month's rent is ₱3,500.00 (the MOA deposit is one month). Please check the amounts.",
            $warning,
        );
    }

    public function test_default_due_day_is_the_move_in_day(): void
    {
        $service = new LeaseService;

        $this->assertSame(25, $service->defaultDueDay('2026-06-25'));
        $this->assertSame(31, $service->defaultDueDay('2026-01-31'));
    }
}
