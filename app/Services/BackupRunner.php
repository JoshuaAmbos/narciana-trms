<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs the MySQL command-line tools for backups: mysqldump (save the database to a .sql file) and
 * mysql (load a .sql file back). Paths come from BACKUP_MYSQLDUMP_PATH / BACKUP_MYSQL_PATH in .env.
 * The database password is put in a temporary options file, never on the command line
 * (where other users of a shared server could see it).
 */
class BackupRunner
{
    /** Save the whole database (except Laravel's session/cache tables) to $file. */
    public function dump(string $file): void
    {
        $db = $this->connection();
        $ignore = array_map(fn (string $t) => '--ignore-table='.$db['database'].'.'.$t, config('narciana.backup.skip_tables'));

        $this->withOptionsFile($db, function (string $options) use ($db, $ignore, $file) {
            $process = new Process([
                config('narciana.backup.mysqldump'),
                '--defaults-extra-file='.$options, // must be the first option
                '--single-transaction',            // consistent copy without locking the tables
                '--no-tablespaces',                // shared hosting users lack the PROCESS privilege
                '--default-character-set=utf8mb4',
                '--add-drop-table',
                ...$ignore,
                '--result-file='.$file,
                $db['database'],
            ]);
            $this->run($process, 'mysqldump');
        });

        if (! is_file($file) || filesize($file) === 0) {
            throw new RuntimeException('mysqldump did not write the backup file.');
        }
    }

    /** Load a .sql file into the database (replaces every table that is in the file). */
    public function import(string $file): void
    {
        $db = $this->connection();
        $input = fopen($file, 'rb');

        try {
            $this->withOptionsFile($db, function (string $options) use ($db, $input) {
                $process = new Process([
                    config('narciana.backup.mysql'),
                    '--defaults-extra-file='.$options,
                    '--default-character-set=utf8mb4',
                    $db['database'],
                ]);
                $process->setInput($input);
                $this->run($process, 'mysql');
            });
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
        }
    }

    private function connection(): array
    {
        return config('database.connections.'.config('database.default'));
    }

    /** Write user/password/host to a temporary [client] options file, call $callback with its path, then delete it. */
    private function withOptionsFile(array $db, callable $callback): void
    {
        $quote = fn ($v) => '"'.addcslashes((string) $v, '"\\').'"';
        $lines = ['[client]', 'user='.$quote($db['username']), 'password='.$quote($db['password'] ?? '')];
        if (! empty($db['unix_socket'])) {
            $lines[] = 'socket='.$quote($db['unix_socket']);
        } else {
            $lines[] = 'host='.$quote($db['host']);
            $lines[] = 'port='.(int) $db['port'];
        }

        $path = tempnam(sys_get_temp_dir(), 'nrc');
        file_put_contents($path, implode("\n", $lines)."\n");
        @chmod($path, 0600);

        try {
            $callback($path);
        } finally {
            @unlink($path);
        }
    }

    private function run(Process $process, string $tool): void
    {
        $process->setTimeout(600);

        try {
            $process->run();
        } catch (\Throwable $e) {
            throw new RuntimeException("Could not run {$tool}: check its path in .env. ".$e->getMessage(), 0, $e);
        }

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            throw new RuntimeException("{$tool} failed (exit code {$process->getExitCode()}): ".mb_substr($error, 0, 300));
        }
    }
}
