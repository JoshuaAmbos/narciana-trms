<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Visitor log entry (MOA two-day visitor rule). */
class Visitor extends Model
{
    protected $table = 'tbl_visitor';

    protected $primaryKey = 'visitor_id';

    public $timestamps = false;

    protected $fillable = [
        'lease_id',
        'visitor_name',
        'relationship',
        'arrival_date',
        'departure_date',
    ];

    protected function casts(): array
    {
        return [
            'arrival_date' => 'date',
            'departure_date' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }
}
