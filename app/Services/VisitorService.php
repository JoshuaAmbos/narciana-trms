<?php

namespace App\Services;

use App\Models\Visitor;
use Illuminate\Support\Carbon;

/**
 * MOA two-day visitor rule.
 * Stay = (departure date, or today if still there) − arrival date, in days.
 * Flag when the stay is more than 2 days: Sep 1 → Sep 3 = 2 days (OK); Sep 1 → Sep 4 = 3 days (flagged).
 */
class VisitorService
{
    public const MAX_DAYS = 2;

    public function stayDays(Visitor $visitor, ?Carbon $today = null): int
    {
        $end = $visitor->departure_date ?? ($today ?? today());

        return (int) $visitor->arrival_date->copy()->startOfDay()->diffInDays($end->copy()->startOfDay(), false);
    }

    public function isOverStay(Visitor $visitor, ?Carbon $today = null): bool
    {
        return $this->stayDays($visitor, $today) > self::MAX_DAYS;
    }
}
