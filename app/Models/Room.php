<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Rentable room and its availability. */
class Room extends Model
{
    public const TYPES = ['Aircon', 'Basic'];

    public const STATUSES = ['Vacant', 'Occupied'];

    /** Business rule: at most 2 occupants per room (or global ceiling from settings). */
    public const MAX_OCCUPANTS = 2;

    /** Global ceiling setting from tbl_setting, defaulting to 2. */
    public static function maxCapacity(): int
    {
        return (int) (Setting::current()->default_room_capacity ?? self::MAX_OCCUPANTS);
    }

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
        'is_rentable',
        'disabled_reason',
        'expected_vacant_date',
    ];

    protected function casts(): array
    {
        return [
            'is_metered' => 'boolean',
            'is_rentable' => 'boolean',
            'max_occupants' => 'integer',
            'monthly_rent' => 'decimal:2',
            'expected_vacant_date' => 'date',
        ];
    }

    /** Vacant and rentable rooms can be offered to waiting prospects. */
    public function isAvailable(): bool
    {
        return $this->status === 'Vacant' && (bool) ($this->is_rentable ?? true);
    }

    public function isRentable(): bool
    {
        return (bool) ($this->is_rentable ?? true);
    }

    public function isVacating(): bool
    {
        return $this->status === 'Occupied' && $this->expected_vacant_date !== null;
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'room_id');
    }

    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class, 'room_id')->where('status', 'Active');
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
