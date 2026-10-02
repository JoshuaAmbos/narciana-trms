<?php

namespace Tests\Support;

use App\Services\BackupRunner;
use RuntimeException;

/**
 * Stands in for mysqldump / mysql in feature tests. A real restore cannot run inside a test, because the
 * test's own database transaction would block (or be wiped by) the import.
 */
class FakeBackupRunner extends BackupRunner
{
    public array $imported = [];

    public bool $failDump = false;

    public function dump(string $file): void
    {
        if ($this->failDump) {
            file_put_contents($file, '-- half'); // like a dump that stopped part-way
            throw new RuntimeException('mysqldump failed (exit code 2): Access denied');
        }
        file_put_contents($file, "-- fake dump\nCREATE TABLE `tbl_setting` (setting_id int);\n");
    }

    public function import(string $file): void
    {
        $this->imported[] = basename($file);
    }
}
