<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data every installation needs (safe to run on the live hosting):
 * the three staff accounts and the default rates/rules row.
 *
 * Sample rooms for testing are in DemoSeeder:  php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
