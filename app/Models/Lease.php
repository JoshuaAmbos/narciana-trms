<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Signed MOA (Contract of Rent) reference details. */
class Lease extends Model
{
    public const STATUSES = ['Active', 'Ended', 'Cancelled'];

    /** Default minimum stay on the MOA form (decision 3). */
    public const DEFAULT_MIN_STAY_MONTHS = 4;

    protected $table = 'tbl_lease';

    protected $primaryKey = 'lease_id';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'room_id',
        'moa_date',
        'move_in_date',
        'due_day',
        'monthly_rent',
        'min_stay_months',
        'advance_amount',
        'deposit_maintenance',
        'deposit_damage',
        'deposit_bills',
        'deposit_keys',
        'status',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'moa_date' => 'date',
            'move_in_date' => 'date',
            'due_day' => 'integer',
            'monthly_rent' => 'decimal:2',
            'min_stay_months' => 'integer',
            'advance_amount' => 'decimal:2',
            'deposit_maintenance' => 'decimal:2',
            'deposit_damage' => 'decimal:2',
            'deposit_bills' => 'decimal:2',
            'deposit_keys' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Sum of the 4 deposit parts (maintenance + damage + bills + keys). */
    public function depositTotal(): float
    {
        return round((float) $this->deposit_maintenance + (float) $this->deposit_damage
            + (float) $this->deposit_bills + (float) $this->deposit_keys, 2);
    }

    /** "1st", "2nd", "25th"... for "Every 25th". */
    public function dueDayLabel(): string
    {
        $d = (int) $this->due_day;
        $suffix = in_array($d % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$d % 10] ?? 'th');

        return $d.$suffix;
    }

    public function principal(): HasOne
    {
        return $this->hasOne(Occupant::class, 'lease_id')->where('is_principal', true);
    }

    public function activeOccupants(): HasMany
    {
        return $this->occupants()->whereNull('date_removed');
    }

    public function activeAppliances(): HasMany
    {
        return $this->appliances()->whereNull('date_removed');
    }

    public function occupants(): HasMany
    {
        return $this->hasMany(Occupant::class, 'lease_id');
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class, 'lease_id');
    }

    public function appliances(): HasMany
    {
        return $this->hasMany(Appliance::class, 'lease_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'lease_id');
    }

    public function concerns(): HasMany
    {
        return $this->hasMany(Concern::class, 'lease_id');
    }

    public function moveout(): HasOne
    {
        return $this->hasOne(Moveout::class, 'lease_id');
    }
}
