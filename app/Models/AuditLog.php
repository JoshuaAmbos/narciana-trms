<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** Record of an important user action. */
class AuditLog extends Model
{
    protected $table = 'tbl_audit_log';

    protected $primaryKey = 'log_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'logged_at' => 'datetime',
        ];
    }

    /**
     * Write one audit entry for the logged-in user (or $userId), stamped with Manila time.
     * Example: AuditLog::record('User created', 'tbl_user', $user->user_id);
     */
    public static function record(string $action, string $table, int $recordId, ?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'table_name' => $table,
            'record_id' => $recordId,
            'logged_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
