<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tenant master identity profile.
 * Represents an individual natural person independent of specific lease contracts.
 */
class Tenant extends Model
{
    public const STATUSES = ['Active', 'Inactive', 'Blacklisted'];

    public const ID_TYPES = [
        'National ID (PhilSys)',
        'Driver\'s License',
        'UMID',
        'Passport',
        'PRC ID',
        'Postal ID',
        'Voter\'s ID',
        'Student ID',
        'Other Valid ID',
    ];

    protected $table = 'tbl_tenant';

    protected $primaryKey = 'tenant_id';

    public $timestamps = true;

    protected $fillable = [
        'full_name',
        'contact_no',
        'valid_id_type',
        'valid_id_no',
        'provincial_address',
        'occupation',
        'prev_building',
        'status',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** Historical and active lease agreements associated with this tenant. */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }

    /** The currently ongoing tenancy, if one exists. */
    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class, 'tenant_id')->where('status', 'Active');
    }

    public function hasActiveLease(): bool
    {
        return $this->leases()->where('status', 'Active')->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', 'Inactive');
    }

    public function scopeBlacklisted(Builder $query): Builder
    {
        return $query->where('status', 'Blacklisted');
    }
}
