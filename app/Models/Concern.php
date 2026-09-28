<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tenant concern or maintenance request. */
class Concern extends Model
{
    protected $table = 'tbl_concern';

    protected $primaryKey = 'concern_id';

    public $timestamps = false;

    protected $fillable = [
        'room_id',
        'lease_id',
        'reported_at',
        'channel',
        'category',
        'priority',
        'description',
        'photo_path',
        'assigned_to',
        'status',
        'resolution',
        'cost',
        'charged_to_tenant',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'cost' => 'decimal:2',
            'charged_to_tenant' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class, 'lease_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
