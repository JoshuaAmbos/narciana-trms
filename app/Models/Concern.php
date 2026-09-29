<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Tenant concern or maintenance request. */
class Concern extends Model
{
    public const CHANNELS = ['Verbal', 'Text', 'Group chat'];

    public const CATEGORIES = ['Maintenance', 'Professional repair', 'Shared area', 'Disturbance', 'Billing', 'Damage'];

    public const PRIORITIES = ['Routine', 'Urgent'];

    public const STATUSES = ['Open', 'In progress', 'Resolved'];

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
        'bill_id',
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

    /** "Room 206" or "Shared area" (room_id is empty only for shared-area concerns). */
    public function location(): string
    {
        return $this->room ? 'Room '.$this->room->room_no : 'Shared area';
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function isBilled(): bool
    {
        return $this->bill_id !== null;
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
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
