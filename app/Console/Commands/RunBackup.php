<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

/** Daily (scheduled in routes/console.php): save the database to storage/app/backups and keep the newest 30. */
class RunBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Back up the database with mysqldump (keeps the newest 30 backups)';

    public function handle(BackupService $backups): int
    {
        try {
            $backup = $backups->create('Scheduled', null);
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup saved: {$backup['name']} (".BackupService::humanSize($backup['size']).').');

        return self::SUCCESS;
    }
}
