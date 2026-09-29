<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Prospective tenant inquiry. */
class Inquiry extends Model
{
    public const TYPES = ['Aircon', 'Basic', 'Any'];

    public const CHANNELS = ['Walk-in', 'Call'];

    public const STATUSES = ['Waiting', 'Rented', 'Referred', 'Declined'];

    protected $table = 'tbl_inquiry';

    protected $primaryKey = 'inquiry_id';

    public $timestamps = false;

    protected $fillable = [
        'prospect_name',
        'contact_no',
        'preferred_type',
        'no_of_occupants',
        'channel',
        'via_representative',
        'inquiry_date',
        'status',
        'room_id',
        'user_id',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'no_of_occupants' => 'integer',
            'via_representative' => 'boolean',
            'inquiry_date' => 'date',
        ];
    }

    /** Link that starts a phone call on the tablet, e.g. "tel:09175550142". */
    public function telHref(): string
    {
        return 'tel:'.preg_replace('/[^0-9+]/', '', $this->contact_no);
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
