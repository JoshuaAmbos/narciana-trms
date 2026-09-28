<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Move-out notice and settlement. */
class Moveout extends Model
{
    protected $table = 'tbl_moveout';

    protected $primaryKey = 'moveout_id';

    public $timestamps = false;

    protected $fillable = [
        'lease_id',
        'notice_date',
        'planned_date',
        'actual_date',
        'early_departure',
        'unpaid_balance',
        'damage_total',
        'refund_amount',
        'excess_amount',
        'refund_due_date',
        'status',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'notice_date' => 'date',
            'planned_date' => 'date',
            'actual_date' => 'date',
            'early_departure' => 'boolean',
            'unpaid_balance' => 'decimal:2',
            'damage_total' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'excess_amount' => 'decimal:2',
            'refund_due_date' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MoveoutItem::class, 'moveout_id');
    }
}
