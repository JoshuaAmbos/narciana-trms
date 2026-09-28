<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Itemized damage found at move-out. */
class MoveoutItem extends Model
{
    protected $table = 'tbl_moveout_item';

    protected $primaryKey = 'item_id';

    public $timestamps = false;

    protected $fillable = [
        'moveout_id',
        'description',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
        ];
    }

    public function moveout(): BelongsTo
    {
        return $this->belongsTo(Moveout::class, 'moveout_id');
    }
}
