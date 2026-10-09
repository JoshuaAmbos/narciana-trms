<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Monthly billing statement for a lease.
 *
 * @property int $bill_id
 * @property int $lease_id
 * @property int|null $reading_id
 * @property \Illuminate\Support\Carbon $period_start
 * @property \Illuminate\Support\Carbon $due_date
 * @property string $rent_amount
 * @property string $water_amount
 * @property string $electricity_amount
 * @property string $appliance_amount
 * @property string $penalty_amount
 * @property string $other_charges
 * @property string $total_amount
 * @property string $status
 * @property bool $is_initial
 * @property int|null $user_id
 */
class Bill extends Model
{
    public const STATUSES = ['Unpaid', 'Paid', 'Overdue'];

    protected $table = 'tbl_bill';

    protected $primaryKey = 'bill_id';

    public $timestamps = false;

    protected $fillable = [
        'lease_id',
        'reading_id',
        'period_start',
        'due_date',
        'rent_amount',
        'water_amount',
        'electricity_amount',
        'appliance_amount',
        'penalty_amount',
        'other_charges',
        'total_amount',
        'status',
        'is_initial',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'due_date' => 'date',
            'is_initial' => 'boolean',
            'rent_amount' => 'decimal:2',
            'water_amount' => 'decimal:2',
            'electricity_amount' => 'decimal:2',
            'appliance_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function meterReading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class, 'reading_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Concerns charged to the tenant on this bill (they make up other_charges). */
    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class, 'bill_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'bill_id');
    }
}
