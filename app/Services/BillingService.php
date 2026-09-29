<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Monthly bills (decision 13: rent ahead, utilities behind).
 *
 * A bill due on day D charges:
 *   rent        = the lease's monthly rent (for the month starting on D)
 *   water       = water_rate × occupants staying at any time in the period (previous due date → D − 1)
 *   metered room (is_metered = 1):  electricity = (current − previous reading) × rate per kWh, no appliances
 *   other rooms  (is_metered = 0):  appliances  = appliance_fee × appliances present in the period, no electricity
 *   penalty = 0 (Module 6), other charges = 0 (Module 7)
 */
class BillingService
{
    /** How many days before the due date a bill may be generated. */
    public const DAYS_AHEAD = 7;

    // ---------- Dates (pure) ----------

    /** Day $day of the month of $month, clamped to that month's last day (31 in April -> 30). */
    public function dueDateIn(int $day, Carbon $month): Carbon
    {
        $first = $month->copy()->startOfMonth();

        return $first->day(min($day, $first->daysInMonth));
    }

    /** First due date: the due day in the month after the move-in month. */
    public function firstDueDate(Carbon $moveIn, int $dueDay): Carbon
    {
        return $this->dueDateIn($dueDay, $moveIn->copy()->startOfMonth()->addMonthNoOverflow());
    }

    /**
     * Utilities period of the bill due on $dueDate: from the previous due date (or the move-in date for the
     * first bill) to the day before $dueDate.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodFor(Carbon $moveIn, int $dueDay, Carbon $dueDate): array
    {
        $first = $this->firstDueDate($moveIn, $dueDay);
        $start = $dueDate->equalTo($first)
            ? $moveIn->copy()->startOfDay()
            : $this->dueDateIn($dueDay, $dueDate->copy()->startOfMonth()->subMonthNoOverflow());

        return [$start, $dueDate->copy()->subDay()];
    }

    // ---------- Money (pure) ----------

    /**
     * All bill components, rounded to centavos. $kwhUsed is ignored for rooms without a meter,
     * $appliances is ignored for metered rooms.
     */
    public function computeAmounts(
        float $rent, float $waterRate, int $occupants, bool $isMetered,
        float $kwhUsed, float $kwhRate, float $applianceFee, int $appliances,
    ): array {
        $amounts = [
            'rent_amount' => round($rent, 2),
            'water_amount' => round($waterRate * $occupants, 2),
            'electricity_amount' => $isMetered ? round($kwhUsed * $kwhRate, 2) : 0.0,
            'appliance_amount' => $isMetered ? 0.0 : round($applianceFee * $appliances, 2),
            'penalty_amount' => 0.0,
            'other_charges' => 0.0,
        ];
        $amounts['total_amount'] = round(array_sum($amounts), 2);

        return $amounts;
    }

    // ---------- Lease-level (database) ----------

    /** The oldest month not billed yet (bills are generated in order). */
    public function nextDueDate(Lease $lease): Carbon
    {
        $last = $lease->bills()->orderByDesc('due_date')->first();

        return $last
            ? $this->dueDateIn($lease->due_day, $last->due_date->copy()->startOfMonth()->addMonthNoOverflow())
            : $this->firstDueDate($lease->move_in_date, $lease->due_day);
    }

    public function canGenerateNow(Lease $lease, ?Carbon $today = null): bool
    {
        return $lease->status === 'Active'
            && $this->nextDueDate($lease)->lte(($today ?? today())->copy()->addDays(self::DAYS_AHEAD));
    }

    public function occupantCount(Lease $lease, Carbon $start, Carbon $end): int
    {
        return $lease->occupants()
            ->whereDate('date_added', '<=', $end)
            ->where(fn ($q) => $q->whereNull('date_removed')->orWhereDate('date_removed', '>', $start))
            ->count();
    }

    public function applianceCount(Lease $lease, Carbon $start, Carbon $end): int
    {
        return $lease->appliances()
            ->whereDate('date_added', '<=', $end)
            ->where(fn ($q) => $q->whereNull('date_removed')->orWhereDate('date_removed', '>', $start))
            ->count();
    }

    /** The room's last recorded current reading (the default "previous reading"), or null. */
    public function lastReading(int $roomId): ?MeterReading
    {
        return MeterReading::where('room_id', $roomId)->orderByDesc('reading_date')->orderByDesc('reading_id')->first();
    }

    /**
     * Everything the statement shows, without saving. $reading = ['previous_reading', 'current_reading',
     * 'reading_date', 'source'] for metered rooms (may be null before the reading is entered).
     */
    public function preview(Lease $lease, ?array $reading = null): array
    {
        $lease->loadMissing('room');
        $settings = Setting::current();
        $dueDate = $this->nextDueDate($lease);
        [$start, $end] = $this->periodFor($lease->move_in_date, $lease->due_day, $dueDate);
        $metered = (bool) $lease->room->is_metered;

        $occupants = $this->occupantCount($lease, $start, $end);
        $appliances = $metered ? 0 : $this->applianceCount($lease, $start, $end);
        $kwh = ($metered && $reading) ? round((float) $reading['current_reading'] - (float) $reading['previous_reading'], 2) : 0.0;

        return [
            'due_date' => $dueDate,
            'period_start' => $start,
            'period_end' => $end,
            'rent_from' => $dueDate,
            'rent_to' => $this->dueDateIn($lease->due_day, $dueDate->copy()->startOfMonth()->addMonthNoOverflow())->subDay(),
            'metered' => $metered,
            'occupants' => $occupants,
            'appliances' => $appliances,
            'water_rate' => (float) $settings->water_rate,
            'appliance_fee' => (float) $settings->appliance_fee,
            'kwh_rate' => (float) $settings->kwh_rate,
            'kwh_used' => $kwh,
            'reading' => $reading,
            'amounts' => $this->computeAmounts(
                (float) $lease->monthly_rent, (float) $settings->water_rate, $occupants, $metered,
                $kwh, (float) $settings->kwh_rate, (float) $settings->appliance_fee, $appliances,
            ),
        ];
    }

    /**
     * Generate the next bill of the lease in one transaction (reading + bill + audit).
     * $expectedDueDate guards against a double submit or a stale form.
     */
    public function generate(Lease $lease, ?array $reading, string $expectedDueDate, User $by, ?Carbon $today = null): Bill
    {
        return DB::transaction(function () use ($lease, $reading, $expectedDueDate, $by, $today) {
            $lease = Lease::whereKey($lease->lease_id)->lockForUpdate()->firstOrFail();

            if ($lease->status !== 'Active') {
                throw ValidationException::withMessages(['lease' => 'Only an active lease can be billed.']);
            }
            if ($this->nextDueDate($lease)->toDateString() !== $expectedDueDate) {
                throw ValidationException::withMessages(['lease' => 'This bill was already generated (or the page is out of date). Open Billing again.']);
            }
            if (! $this->canGenerateNow($lease, $today)) {
                throw ValidationException::withMessages(['lease' => 'This bill can be generated from '.self::DAYS_AHEAD.' days before its due date.']);
            }

            $metered = (bool) $lease->room->is_metered;
            if ($metered && ! $reading) {
                throw ValidationException::withMessages(['current_reading' => 'This room has its own meter: enter the meter reading.']);
            }

            $preview = $this->preview($lease, $metered ? $reading : null);

            $readingModel = null;
            if ($metered) {
                $readingModel = MeterReading::create([
                    'room_id' => $lease->room_id,
                    'reading_date' => $reading['reading_date'],
                    'previous_reading' => $reading['previous_reading'],
                    'current_reading' => $reading['current_reading'],
                    'rate_per_kwh' => $preview['kwh_rate'],
                    'source' => $reading['source'],
                    'user_id' => $by->user_id,
                ]);
            }

            $bill = Bill::create($preview['amounts'] + [
                'lease_id' => $lease->lease_id,
                'reading_id' => $readingModel?->reading_id,
                'period_start' => $preview['period_start']->toDateString(),
                'due_date' => $preview['due_date']->toDateString(),
                'status' => 'Unpaid',
                'user_id' => $by->user_id,
            ]);

            AuditLog::record('Bill generated', 'tbl_bill', $bill->bill_id, $by->user_id);

            return $bill;
        });
    }

    /**
     * Statement data of a saved bill, in the same shape as preview(). Amounts are the stored ones; counts are
     * recounted from the (append-only) occupant/appliance history, and unit prices are amount ÷ count, so an old
     * statement still shows what was charged even if Settings changed later.
     */
    public function statementForBill(Bill $bill): array
    {
        $bill->loadMissing(['lease.room', 'meterReading']);
        $lease = $bill->lease;
        $start = $bill->period_start->copy();
        $end = $bill->due_date->copy()->subDay();
        $metered = $bill->reading_id !== null; // every metered bill is saved with its meter reading
        $occupants = $this->occupantCount($lease, $start, $end);
        $appliances = $metered ? 0 : $this->applianceCount($lease, $start, $end);
        $reading = $bill->meterReading;

        return [
            'due_date' => $bill->due_date,
            'period_start' => $start,
            'period_end' => $end,
            'rent_from' => $bill->due_date,
            'rent_to' => $this->dueDateIn($lease->due_day, $bill->due_date->copy()->startOfMonth()->addMonthNoOverflow())->subDay(),
            'metered' => $metered,
            'occupants' => $occupants,
            'appliances' => $appliances,
            'water_rate' => $occupants ? round((float) $bill->water_amount / $occupants, 2) : 0.0,
            'appliance_fee' => $appliances ? round((float) $bill->appliance_amount / $appliances, 2) : 0.0,
            'kwh_rate' => $reading ? (float) $reading->rate_per_kwh : 0.0,
            'kwh_used' => $reading ? round((float) $reading->current_reading - (float) $reading->previous_reading, 2) : 0.0,
            'reading' => $reading ? [
                'previous_reading' => $reading->previous_reading, 'current_reading' => $reading->current_reading,
                'reading_date' => $reading->reading_date->toDateString(), 'source' => $reading->source,
            ] : null,
            'amounts' => [
                'rent_amount' => (float) $bill->rent_amount, 'water_amount' => (float) $bill->water_amount,
                'electricity_amount' => (float) $bill->electricity_amount, 'appliance_amount' => (float) $bill->appliance_amount,
                'penalty_amount' => (float) $bill->penalty_amount, 'other_charges' => (float) $bill->other_charges,
                'total_amount' => (float) $bill->total_amount,
            ],
        ];
    }

    public function canDelete(Bill $bill): bool
    {
        return $bill->status === 'Unpaid' && $bill->payments()->doesntExist();
    }

    /** Delete an Unpaid bill with no payment (and the meter reading made for it). */
    public function delete(Bill $bill, User $by): void
    {
        DB::transaction(function () use ($bill, $by) {
            $bill = Bill::whereKey($bill->bill_id)->lockForUpdate()->firstOrFail();
            if (! $this->canDelete($bill)) {
                throw ValidationException::withMessages(['bill' => 'Only an Unpaid bill without payments can be deleted.']);
            }
            if ($bill->lease->bills()->where('due_date', '>', $bill->due_date)->exists()) {
                throw ValidationException::withMessages(['bill' => 'Delete the newer bills of this lease first (bills stay in order).']);
            }

            $readingId = $bill->reading_id;
            $billId = $bill->bill_id;
            $bill->delete();
            if ($readingId && ! Bill::where('reading_id', $readingId)->exists()) {
                MeterReading::whereKey($readingId)->delete();
            }

            AuditLog::record('Bill deleted', 'tbl_bill', $billId, $by->user_id);
        });
    }
}
