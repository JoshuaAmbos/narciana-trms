<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Declared occupant of a lease (used for water billing). */
class Occupant extends Model
{
    protected $table = 'tbl_occupant';

    protected $primaryKey = 'occupant_id';

    public $timestamps = false;

    protected $fillable = [
        'lease_id',
        'full_name',
        'is_principal',
        'date_added',
        'date_removed',
    ];

    protected function casts(): array
    {
        return [
            'is_principal' => 'boolean',
            'date_added' => 'date',
            'date_removed' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }
}
