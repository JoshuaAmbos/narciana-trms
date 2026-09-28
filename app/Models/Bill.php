<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Monthly billing statement for a lease. */
class Bill extends Model
{
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
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'due_date' => 'date',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'bill_id');
    }
}
