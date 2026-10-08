<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Business rates and rules set by the Owner (a single row). */
class Setting extends Model
{
    protected $table = 'tbl_setting';

    protected $primaryKey = 'setting_id';

    public $timestamps = false;

    public const PENALTY_BASES = ['Per day of rent', 'Flat % of total'];

    protected $fillable = [
        'water_rate',
        'appliance_fee',
        'standard_basic_rent',
        'standard_aircon_rent',
        'kwh_rate',
        'grace_days',
        'penalty_basis',
        'penalty_rate',
        'refund_days',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'water_rate' => 'decimal:2',
            'appliance_fee' => 'decimal:2',
            'standard_basic_rent' => 'decimal:2',
            'standard_aircon_rent' => 'decimal:2',
            'kwh_rate' => 'decimal:2',
            'grace_days' => 'integer',
            'penalty_rate' => 'decimal:2',
            'refund_days' => 'integer',
        ];
    }

    public const DEFAULTS = [
        'water_rate' => 200.00,
        'appliance_fee' => 200.00,
        'standard_basic_rent' => 3000.00,
        'standard_aircon_rent' => 3600.00,
        'kwh_rate' => 15.00,
        'grace_days' => 3,
        'penalty_basis' => 'Per day of rent',
        'penalty_rate' => 1.00,
        'refund_days' => 7,
    ];

    /** The single rates-and-rules row (created by SettingSeeder or returned with system defaults). */
    public static function current(): self
    {
        return self::query()->first() ?? new self(self::DEFAULTS);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
