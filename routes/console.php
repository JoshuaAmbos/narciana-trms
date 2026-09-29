<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled tasks. On the hosting, one cron job runs Laravel's scheduler every minute:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
| (Backups are added here in Module 9.)
*/

// Unpaid bills past due date + grace become Overdue (also refreshed whenever Billing/Payments open).
Schedule::command('bills:mark-overdue')->dailyAt('00:05')->timezone('Asia/Manila');
