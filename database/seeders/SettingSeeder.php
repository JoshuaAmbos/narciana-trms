<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The single row of business rates and rules. The Owner can change these later in Settings.
 * Penalty default (decision 1): 1% of monthly rent per day late, counted after a 3-day grace period.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        if (Setting::exists()) {
            $this->command?->line('Settings row already exists, skipped.');

            return;
        }

        Setting::create([
            'water_rate' => 200.00,
            'appliance_fee' => 200.00,
            'standard_basic_rent' => 3000.00,
            'standard_aircon_rent' => 3600.00,
            'kwh_rate' => 12.00,
            'grace_days' => 3,
            'penalty_basis' => 'Per day of rent',
            'penalty_rate' => 1.00,
            'refund_days' => 7,
            'updated_by' => User::where('role', 'Owner')->value('user_id'),
        ]);

        $this->command?->info('Created the default settings row.');
    }
}
