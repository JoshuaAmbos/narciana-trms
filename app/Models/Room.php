<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rentable room and its availability. */
class Room extends Model
{
    protected $table = 'tbl_room';

    protected $primaryKey = 'room_id';

    public $timestamps = false;

    protected $fillable = [
        'room_no',
        'room_type',
        'is_metered',
        'max_occupants',
        'monthly_rent',
        'status',
        'expected_vacant_date',
    ];

    protected function casts(): array
    {
        return [
            'is_metered' => 'boolean',
            'max_occupants' => 'integer',
            'monthly_rent' => 'decimal:2',
            'expected_vacant_date' => 'date',
        ];
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'room_id');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'room_id');
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'room_id');
    }

    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class, 'room_id');
    }
}
