<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** Tenant concern or maintenance request. */
class Concern extends Model
{
    public const CHANNELS = ['Verbal', 'Text', 'Group chat'];

    /** Category => short description shown on the form (decision 18). */
    public const CATEGORIES = [
        'Plumbing' => 'Clogged toilet or sink, leaking pipe or faucet.',
        'Electrical' => 'No power, circuit breaker tripping (the breaker cuts the power because of an overload, '
            .'e.g. too many appliances at once, or a short circuit), outlet not working.',
        'Lights' => 'Busted bulb or light not turning on.',
        'Aircon' => 'Aircon not cooling, leaking or needs cleaning.',
        'Door/Lock/Keys' => 'Lost key, broken lock or door knob, door that will not close.',
        'Water supply' => 'No water or low water pressure.',
        'Pest control' => 'Cockroaches, ants, rats, bed bugs or other pests.',
        'Noise/Disturbance' => 'Noise or disturbance from other tenants or visitors (e.g. after quiet hours).',
        'Cleanliness (shared areas)' => 'Dirty CR, hallway, stairs or garbage area.',
        'Damage to room or furniture' => 'Damaged wall, bed, cabinet, window or other room fixture.',
        'Billing concern' => 'Question or complaint about a bill or payment.',
        'Other' => 'Anything not listed above (describe it below).',
    ];

    /** This category is always about a shared area (no room). */
    public const SHARED_CATEGORY = 'Cleanliness (shared areas)';

    /** Photos: JPG or PNG, at most 5 MB (decision 18). */
    public const PHOTO_KINDS = ['reported' => 'photo_path', 'resolved' => 'resolution_photo_path'];

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
        'resolution_photo_path',
        'reported_by',
        'assigned_to',
        'started_at',
        'started_by',
        'status',
        'resolution',
        'cost',
        'charged_to_tenant',
        'bill_id',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'cost' => 'decimal:2',
            'charged_to_tenant' => 'boolean',
            'resolved_at' => 'datetime',
            'started_at' => 'datetime',
        ];
    }

    /** Who reported it defaults to the logged-in user (else the assigned user) when not given. */
    protected static function booted(): void
    {
        static::creating(function (Concern $concern) {
            $concern->reported_by ??= Auth::id() ?? $concern->assigned_to;
        });
    }

    /** "Room 206" or "Shared area" (room_id is empty only for shared-area concerns). */
    public function location(): string
    {
        return $this->room ? 'Room '.$this->room->room_no : 'Shared area';
    }

    /** Photos are private: served by ConcernController@photo to logged-in users only. $kind = reported | resolved */
    public function photoUrl(string $kind = 'reported'): ?string
    {
        return $this->{self::PHOTO_KINDS[$kind]} ? route('concerns.photo', [$this, $kind]) : null;
    }

    /**
     * Progress timeline: Reported -> In progress -> Resolved, each with when / who (null = not reached).
     * A concern resolved straight from Open shows In progress as skipped.
     */
    public function timeline(): array
    {
        $resolved = $this->status === 'Resolved';

        return [
            ['label' => 'Reported', 'at' => $this->reported_at, 'by' => $this->reportedBy, 'done' => true],
            ['label' => 'In progress', 'at' => $this->started_at, 'by' => $this->startedBy,
                'done' => $this->started_at !== null, 'skipped' => $resolved && $this->started_at === null],
            ['label' => 'Resolved', 'at' => $this->resolved_at, 'by' => $this->resolvedBy, 'done' => $resolved],
        ];
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

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
