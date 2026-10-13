<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use LogicException;

/** Record of an important user action. Entries are written once and never edited or deleted. */
class AuditLog extends Model
{
    /** Friendly names of the tables an entry can point to (used by the Audit Log viewer). */
    public const TABLE_LABELS = [
        'tbl_user' => 'User', 'tbl_room' => 'Room', 'tbl_inquiry' => 'Inquiry', 'tbl_tenant' => 'Tenant',
        'tbl_lease' => 'Lease', 'tbl_bill' => 'Bill', 'tbl_payment' => 'Payment', 'tbl_concern' => 'Concern',
        'tbl_moveout' => 'Move-out', 'tbl_setting' => 'Settings', 'backup' => 'Backup file',
    ];

    protected $table = 'tbl_audit_log';

    protected $primaryKey = 'log_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'details',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'logged_at' => 'datetime',
        ];
    }

    /** Audit entries are read-only: no one (not even the Owner, not even code) may change or delete one. */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Audit log entries cannot be deleted.'));
    }

    /**
     * Write one audit entry for the logged-in user (or $userId), stamped with Manila time.
     * Example: AuditLog::record('User created', 'tbl_user', $user->user_id);
     *          AuditLog::record('Settings changed', 'tbl_setting', 1, details: 'Water rate: ₱200.00 → ₱250.00');
     */
    public static function record(string $action, string $table, ?int $recordId = 0, ?int $userId = null, ?string $details = null): self
    {
        return self::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId ?? 0,
            'details' => $details === null ? null : Str::limit($details, 497),
            'logged_at' => now(),
        ]);
    }

    /** "Payment voided (ACK-2026-0001)" -> "Payment voided": the action without its bracketed detail. */
    public static function actionType(string $action): string
    {
        return preg_replace('/ \(.*\)$/', '', $action);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
