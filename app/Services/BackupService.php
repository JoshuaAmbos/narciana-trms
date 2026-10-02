<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Backup and restore (Owner only, plus the daily `backup:run`).
 * Each backup is two files in storage/app/backups:
 *   backup-2026-10-02_020000.sql   the mysqldump output
 *   backup-2026-10-02_020000.json  who made it and why (Manual / Scheduled / Safety)
 * The .json lives beside the file (not in the database) so the list stays right even after a restore
 * replaces the database with an older copy.
 */
class BackupService
{
    public const TYPES = ['Manual', 'Scheduled', 'Safety'];

    /** Only names this service makes are accepted, so a request can never reach another file. */
    private const NAME_PATTERN = '/^backup-\d{4}-\d{2}-\d{2}_\d{6}(-\d+)?\.sql$/';

    public function __construct(private BackupRunner $runner) {}

    /**
     * Make a backup now. $by = the Owner who asked, or null for the scheduled one.
     * Returns the backup's details (see list()). Older backups beyond the newest 30 are removed afterwards
     * unless $prune is false.
     */
    public function create(string $type, ?User $by, bool $prune = true): array
    {
        $dir = $this->directory();
        $name = $this->newName();
        $file = $dir.DIRECTORY_SEPARATOR.$name;

        try {
            $this->runner->dump($file);
        } catch (\Throwable $e) {
            File::delete($file); // never leave a half-written backup in the list
            throw $e;
        }

        File::put($this->metaPath($file), json_encode([
            'type' => $type,
            'user_id' => $by?->user_id,
            'by' => $by ? "{$by->full_name} ({$by->role})" : 'Automatic (scheduled)',
            'created_at' => now()->toDateTimeString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $auditUser = $this->auditUserId($by);
        if ($auditUser) {
            AuditLog::record('Backup created', 'backup', 0, $auditUser, "{$type}: {$name} (".self::humanSize(filesize($file)).')');
        }

        if ($prune) {
            $this->prune($auditUser);
        }

        return $this->info($file);
    }

    /** All backups, newest first: name, created_at (Carbon), size (bytes), type, by. */
    public function list(): Collection
    {
        return collect(File::glob($this->directory().DIRECTORY_SEPARATOR.'backup-*.sql'))
            ->filter(fn (string $f) => preg_match(self::NAME_PATTERN, basename($f)))
            ->map(fn (string $f) => $this->info($f))
            ->sortByDesc('name')
            ->values();
    }

    /** Full path of an existing backup, or 404 for any other name. */
    public function path(string $name): string
    {
        $file = $this->directory().DIRECTORY_SEPARATOR.$name;
        if (! preg_match(self::NAME_PATTERN, $name) || ! is_file($file)) {
            throw new NotFoundHttpException('Backup not found.');
        }

        return $file;
    }

    public function delete(string $name, User $by): void
    {
        $file = $this->path($name);
        File::delete([$file, $this->metaPath($file)]);
        AuditLog::record('Backup deleted', 'backup', 0, $by->user_id, $name);
    }

    public function recordDownload(string $name, User $by): void
    {
        AuditLog::record('Backup downloaded', 'backup', 0, $by->user_id, $name);
    }

    /**
     * Replace the database with the chosen backup. A Safety backup of the current data is made first;
     * if that fails, nothing is restored. Returns the safety backup's name.
     * The audit entry is written after the import (the import replaces tbl_audit_log too).
     */
    public function restore(string $name, User $by): string
    {
        $file = $this->path($name);
        $safety = $this->create('Safety', $by, prune: false); // no pruning: it could remove the file being restored

        try {
            $this->runner->import($file);
        } catch (\Throwable $e) {
            throw new RuntimeException("The restore failed and the data may be incomplete. Restore the safety backup {$safety['name']} to go back. ({$e->getMessage()})", 0, $e);
        }

        $auditUser = $this->auditUserId($by); // the Owner's account may not exist in the older copy
        if ($auditUser) {
            AuditLog::record('Backup restored', 'backup', 0, $auditUser, "Restored {$name}; safety backup {$safety['name']}");
        }

        return $safety['name'];
    }

    /** Keep only the newest `narciana.backup.keep` backups. Returns how many were removed. */
    public function prune(?int $auditUserId = null): int
    {
        $old = $this->list()->slice((int) config('narciana.backup.keep'));
        foreach ($old as $backup) {
            $file = $this->directory().DIRECTORY_SEPARATOR.$backup['name'];
            File::delete([$file, $this->metaPath($file)]);
            if ($auditUserId) {
                AuditLog::record('Backup deleted', 'backup', 0, $auditUserId, "{$backup['name']} (automatic: keeping the newest ".config('narciana.backup.keep').')');
            }
        }

        return $old->count();
    }

    public static function humanSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0).' KB',
            default => $bytes.' bytes',
        };
    }

    // ---------- Helpers ----------

    private function directory(): string
    {
        $dir = config('narciana.backup.path');
        File::ensureDirectoryExists($dir);
        // Extra guard in case the app folder is ever placed inside public_html.
        if (! is_file($dir.'/.htaccess')) {
            File::put($dir.'/.htaccess', "Require all denied\n");
        }

        return $dir;
    }

    private function newName(): string
    {
        $base = 'backup-'.now()->format('Y-m-d_His');
        $name = $base.'.sql';
        for ($i = 2; is_file($this->directory().DIRECTORY_SEPARATOR.$name); $i++) {
            $name = "{$base}-{$i}.sql";
        }

        return $name;
    }

    private function metaPath(string $sqlFile): string
    {
        return preg_replace('/\.sql$/', '.json', $sqlFile);
    }

    private function info(string $file): array
    {
        $meta = is_file($this->metaPath($file)) ? (json_decode(File::get($this->metaPath($file)), true) ?: []) : [];
        preg_match('/backup-(\d{4}-\d{2}-\d{2})_(\d{2})(\d{2})(\d{2})/', basename($file), $m);

        return [
            'name' => basename($file),
            'created_at' => Carbon::parse("{$m[1]} {$m[2]}:{$m[3]}:{$m[4]}"),
            'size' => filesize($file),
            'size_text' => self::humanSize(filesize($file)),
            'type' => $meta['type'] ?? 'Unknown',
            'by' => $meta['by'] ?? 'Unknown',
        ];
    }

    /**
     * Audit entries need a user. Use the Owner who acted if their account exists in the (possibly just restored)
     * database; scheduled backups are logged under the first active Owner account.
     */
    private function auditUserId(?User $by): ?int
    {
        if ($by && User::whereKey($by->user_id)->exists()) {
            return $by->user_id;
        }

        return User::where('role', 'Owner')->where('is_active', true)->orderBy('user_id')->value('user_id');
    }
}
