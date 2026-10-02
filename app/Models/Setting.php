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
            'kwh_rate' => 'decimal:2',
            'grace_days' => 'integer',
            'penalty_rate' => 'decimal:2',
            'refund_days' => 'integer',
        ];
    }

    /** The single rates-and-rules row (created by SettingSeeder). */
    public static function current(): self
    {
        return self::query()->firstOrFail();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
