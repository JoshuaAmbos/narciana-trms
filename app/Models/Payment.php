<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Payment received against a bill. */
class Payment extends Model
{
    public const MODES = ['Cash', 'GCash'];

    protected $table = 'tbl_payment';

    protected $primaryKey = 'payment_id';

    public $timestamps = false;

    protected $fillable = [
        'bill_id',
        'amount',
        'payment_date',
        'mode',
        'gcash_ref_no',
        'ack_no',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
