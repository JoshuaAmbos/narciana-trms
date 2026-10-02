<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Move-out notice and settlement. */
class Moveout extends Model
{
    public const STATUSES = ['Pending', 'Refunded', 'Excess billed', 'Settled'];

    protected $table = 'tbl_moveout';

    protected $primaryKey = 'moveout_id';

    public $timestamps = false;

    protected $fillable = [
        'lease_id',
        'notice_date',
        'planned_date',
        'actual_date',
        'inspected_at',
        'early_departure',
        'unpaid_balance',
        'damage_total',
        'refund_amount',
        'excess_amount',
        'refund_due_date',
        'final_bill_id',
        'excess_bill_id',
        'status',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'notice_date' => 'date',
            'planned_date' => 'date',
            'actual_date' => 'date',
            'inspected_at' => 'datetime',
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

    /** Final bill: the last month's utilities (decision 16). */
    public function finalBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'final_bill_id');
    }

    /** Excess bill: what the tenant still owes after the deposit. */
    public function excessBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'excess_bill_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }

    public function items(): HasMany
    {
        return $this->hasMany(MoveoutItem::class, 'moveout_id');
    }
}
