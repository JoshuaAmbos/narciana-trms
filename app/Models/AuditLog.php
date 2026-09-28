<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
