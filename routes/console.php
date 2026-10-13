<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

/*
| Scheduled tasks. On the hosting, one cron job runs Laravel's scheduler every minute:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
| (Namecheap cPanel > Cron Jobs: use the full PHP path, e.g. /usr/local/bin/php, and the app's folder.)
*/

// Daily delinquency settlement (runs at 00:05 PST)
Schedule::command('bills:mark-overdue')
    ->dailyAt('00:05')
    ->timezone('Asia/Manila');

// Dynamic daily backup time resolution
$backupTime = '02:00';
try {
    if (Schema::hasTable('tbl_setting')) {
        $settingTime = DB::table('tbl_setting')->value('backup_time');
        if (!empty($settingTime)) {
            // Normalize "02:00:00" to "H:i" format (e.g., "02:00")
            $backupTime = \Carbon\Carbon::parse($settingTime)->format('H:i');
        }
    }
} catch (\Throwable $e) {
    $backupTime = '02:00';
}

Schedule::command('backup:run')
    ->dailyAt($backupTime)
    ->timezone('Asia/Manila');
