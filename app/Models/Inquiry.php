<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** Prospective tenant inquiry. */
class Inquiry extends Model
{
    public const TYPES = ['Aircon', 'Basic', 'Any'];

    public const CHANNELS = ['Walk-in', 'Call'];

    public const STATUSES = ['Waiting', 'Rented', 'Referred', 'Declined'];

    protected $table = 'tbl_inquiry';

    protected $primaryKey = 'inquiry_id';

    public $timestamps = true;

    protected $fillable = [
        'prospect_name',
        'contact_no',
        'preferred_type',
        'no_of_occupants',
        'channel',
        'via_representative',
        'wants_contact',
        'inquiry_date',
        'status',
        'room_id',
        'user_id',
        'remarks',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'no_of_occupants' => 'integer',
            'via_representative' => 'boolean',
            'wants_contact' => 'boolean',
            'inquiry_date' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Normalized phone number with only digits and optional '+' for native tel: dialer intents.
     */
    public function getFormattedPhoneAttribute(): string
    {
        return preg_replace('/[^0-9+]/', '', (string) $this->contact_no);
    }

    /** Link that starts a phone call on the tablet, e.g. "tel:09175550142". */
    public function telHref(): string
    {
        return 'tel:'.$this->formatted_phone;
    }

    /**
     * Determine if there is at least one matching vacant room for this inquiry.
     */
    public function hasMatchingVacantRoom(?iterable $vacantRooms = null): bool
    {
        if ($this->room_id) {
            return $this->room?->status === 'Vacant';
        }

        if ($vacantRooms === null) {
            $vacantRooms = Room::where('status', 'Vacant')->get();
        }

        if ($vacantRooms instanceof Collection) {
            return match ($this->preferred_type) {
                'Aircon' => $vacantRooms->contains(fn ($r) => $r->room_type === 'Aircon'),
                'Basic' => $vacantRooms->contains(fn ($r) => $r->room_type === 'Basic'),
                default => $vacantRooms->isNotEmpty(),
            };
        }

        foreach ($vacantRooms as $r) {
            if ($this->preferred_type === 'Aircon' && $r->room_type === 'Aircon') {
                return true;
            }
            if ($this->preferred_type === 'Basic' && $r->room_type === 'Basic') {
                return true;
            }
            if ($this->preferred_type === 'Any') {
                return true;
            }
        }

        return false;
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
