<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Appliance registered in a basic room. */
class Appliance extends Model
{
    public const SOURCES = ['Declared', 'CCTV'];

    protected $table = 'tbl_appliance';

    protected $primaryKey = 'appliance_id';

    public $timestamps = false;

    protected $fillable = [
        'lease_id',
        'appliance_name',
        'monthly_fee',
        'date_added',
        'date_removed',
        'source',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
            'date_added' => 'date',
            'date_removed' => 'date',
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
}
