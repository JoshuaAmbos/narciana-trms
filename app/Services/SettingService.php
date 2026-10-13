<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owner changes the rates and rules (tbl_setting). Bills already generated keep the amounts saved on them;
 * only bills generated after the change use the new rates.
 */
class SettingService
{
    /** Field => [label, format] where format is 'peso', 'days', 'percent' or 'text'. */
    public const FIELDS = [
        'water_rate' => ['Water rate (per occupant)', 'peso'],
        'appliance_fee' => ['Appliance fee (per appliance, basic rooms)', 'peso'],
        'standard_basic_rent' => ['Standard Basic room rate', 'peso'],
        'standard_aircon_rent' => ['Standard Aircon room rate', 'peso'],
        'default_room_capacity' => ['Default room capacity', 'occupants'],
        'kwh_rate' => ['Electricity rate (per kWh)', 'peso'],
        'grace_days' => ['Grace period', 'days'],
        'penalty_basis' => ['Penalty basis', 'text'],
        'penalty_rate' => ['Penalty rate', 'percent'],
        'refund_days' => ['Refund deposit within', 'days'],
        'backup_time' => ['Automated daily backup time', 'time'],
    ];

    /**
     * Save the new values. Returns the list of changes as "Label: old → new" (empty = nothing changed,
     * nothing saved, nothing logged).
     */
    public function update(array $data, User $owner): array
    {
        return DB::transaction(function () use ($data, $owner) {
            $setting = Setting::query()->lockForUpdate()->first() ?? new Setting(Setting::DEFAULTS);

            $changes = [];
            foreach (self::FIELDS as $field => [$label, $format]) {
                $old = self::format($setting->{$field}, $format);
                $new = self::format($data[$field], $format);
                if ($old !== $new) {
                    $changes[] = "{$label}: {$old} → {$new}";
                    $setting->{$field} = $data[$field];
                }
            }

            if ($changes) {
                $setting->updated_by = $owner->user_id;
                $setting->save();
                AuditLog::record('Settings changed', 'tbl_setting', $setting->setting_id, $owner->user_id, implode('; ', $changes));
            }

            return $changes;
        });
    }

    public static function format(mixed $value, string $format): string
    {
        return match ($format) {
            'peso' => '₱'.number_format((float) $value, 2),
            'percent' => number_format((float) $value, 2).'%',
            'days' => (int) $value.' '.((int) $value === 1 ? 'day' : 'days'),
            'occupants' => (int) $value.' '.((int) $value === 1 ? 'occupant' : 'occupants'),
            'time' => $value ? \Carbon\Carbon::parse($value)->format('H:i') : '',
            default => (string) $value,
        };
    }
}
