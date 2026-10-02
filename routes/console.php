<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. On the hosting, one cron job runs Laravel's scheduler every minute:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
| (Namecheap cPanel > Cron Jobs: use the full PHP path, e.g. /usr/local/bin/php, and the app's folder.)
*/

// Unpaid bills past due date + grace become Overdue (also refreshed whenever Billing/Payments open).
Schedule::command('bills:mark-overdue')->dailyAt('00:05')->timezone('Asia/Manila');

// Daily full backup of the database (storage/app/backups, newest 30 kept). Every backup is a full mysqldump,
// so this also covers the weekly full backup of decision 5.
Schedule::command('backup:run')->dailyAt('02:00')->timezone('Asia/Manila');
