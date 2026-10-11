<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs the MySQL/MariaDB command-line tools for backups: mysqldump (save the database to a .sql file) and
 * mysql (load a .sql file back).
 * Auto-detects binary paths across Windows XAMPP, Linux LAMPP, Native Linux, and Alpine Docker.
 */
class BackupRunner
{
    /** Save the whole database (except Laravel's session/cache tables) to $file. */
    public function dump(string $file): void
    {
        $db = $this->connection();
        $skipTables = config('narciana.backup.skip_tables', ['sessions', 'cache', 'cache_locks']);
        $ignore = array_map(fn (string $t) => '--ignore-table='.$db['database'].'.'.$t, $skipTables);

        $this->withOptionsFile($db, function (string $options) use ($db, $ignore, $file) {
            $process = new Process([
                $this->resolveBinary('mysqldump'),
                '--defaults-extra-file='.$options, // must be the first option
                '--single-transaction',            // consistent copy without locking the tables
                '--no-tablespaces',                // avoid PROCESS privilege requirements
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
                    $this->resolveBinary('mysql'),
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

    /**
     * Dynamically resolve binary paths across:
     * 1. config('narciana.backup.mysql') or config('narciana.backup.mysqldump')
     * 2. Linux LAMPP (/opt/lampp/bin)
     * 3. Windows XAMPP (C:\xampp\mysql\bin)
     * 4. Native Linux /usr/bin or /usr/local/bin (with MariaDB fallbacks on Alpine)
     */
    private function resolveBinary(string $tool): string
    {
        // 1. Check explicit configuration in config/narciana.php
        $configured = config("narciana.backup.{$tool}");
        if (! empty($configured) && $configured !== $tool) {
            return $configured;
        }

        // 2. Linux LAMPP (/opt/lampp/bin)
        $lamppPath = "/opt/lampp/bin/{$tool}";
        if (is_file($lamppPath) && is_executable($lamppPath)) {
            return $lamppPath;
        }

        // 3. Windows XAMPP
        if (PHP_OS_FAMILY === 'Windows') {
            $xamppPath = "C:\\xampp\\mysql\\bin\\{$tool}.exe";
            if (is_file($xamppPath)) {
                return $xamppPath;
            }
        }

        // 4. Standard Linux paths (includes mariadb alternatives on Alpine)
        $alternatives = $tool === 'mysqldump' 
            ? ['mariadb-dump', 'mysqldump'] 
            : ['mariadb', 'mysql'];

        foreach (['/usr/bin', '/usr/local/bin', '/bin'] as $dir) {
            foreach ($alternatives as $binary) {
                $nativePath = "{$dir}/{$binary}";
                if (is_file($nativePath) && is_executable($nativePath)) {
                    return $nativePath;
                }
            }
        }

        return $tool;
    }

    private function connection(): array
    {
        return config('database.connections.'.config('database.default'));
    }

    /** Write user/password/host to a temporary [client] options file, call $callback with its path, then delete it. */
    private function withOptionsFile(array $db, callable $callback): void
    {
        $quote = fn ($v) => '"'.addcslashes((string) $v, '"\\').'"';
        $lines = [
            '[client]',
            'user='.$quote($db['username']),
            'password='.$quote($db['password'] ?? ''),
            'ssl=1',
            'ssl-verify-server-cert=0', // Bypass self-signed chain failure on cloud hosts like Aiven
        ];

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