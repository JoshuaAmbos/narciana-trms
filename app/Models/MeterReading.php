<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Electric meter reading for an aircon room. */
class MeterReading extends Model
{
    protected $table = 'tbl_meter_reading';

    protected $primaryKey = 'reading_id';

    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'reading_date',
        'previous_reading',
        'current_reading',
        'rate_per_kwh',
        'source',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'previous_reading' => 'decimal:2',
            'current_reading' => 'decimal:2',
            'rate_per_kwh' => 'decimal:2',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'reading_id');
    }
}
