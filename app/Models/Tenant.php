<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant master identity profile.
 * Represents an individual natural person independent of specific lease contracts.
 */
class Tenant extends Model
{
    use SoftDeletes;

    public const STATUSES = ['Active', 'Inactive', 'Blacklisted', 'Archived'];

    public const ACTIVE_STATUSES = ['Active', 'Inactive', 'Blacklisted'];

    public const ID_TYPES = [
        'PhilSys National ID (PCN)',
        'Driver\'s License',
        'UMID',
        'Passport',
        'PRC ID',
        'Postal ID',
        'Voter\'s ID',
        'Student ID',
        'Other',
    ];

    protected $table = 'tbl_tenant';

    protected $primaryKey = 'tenant_id';

    public $timestamps = true;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'name_extension',
        'full_name',
        'contact_no',
        'valid_id_type',
        'other_id_type',
        'valid_id_no',
        'provincial_address',
        'occupation',
        'status',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Compute full name from composite fields with fallback to legacy full_name attribute.
     */
    public function getFullNameAttribute(): string
    {
        $computed = trim("{$this->first_name} {$this->middle_name} {$this->last_name} {$this->name_extension}");
        if ($computed !== '') {
            return preg_replace('/\s+/', ' ', $computed);
        }

        return (string) ($this->attributes['full_name'] ?? '');
    }

    /**
     * Mutator to synchronize first_name and last_name when full_name is assigned directly.
     */
    public function setFullNameAttribute($value): void
    {
        $this->attributes['full_name'] = $value;
        if ($value && (empty($this->attributes['first_name']) || empty($this->attributes['last_name']))) {
            $parts = preg_split('/\s+/', trim((string) $value), 2);
            $this->attributes['first_name'] = $this->attributes['first_name'] ?? ($parts[0] ?? '');
            $this->attributes['last_name'] = $this->attributes['last_name'] ?? ($parts[1] ?? ($parts[0] ?? ''));
        }
    }

    /**
     * Masked ID accessor for summary tables to preserve tenant privacy (RA 10173 compliance).
     */
    public function getMaskedIdNumberAttribute(): string
    {
        $no = (string) $this->valid_id_no;
        $len = strlen($no);
        if ($len <= 4) {
            return $no;
        }

        $stars = max(1, $len - 6);

        return substr($no, 0, 2).str_repeat('*', $stars).substr($no, -4);
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

    public function scopeArchived(Builder $query): Builder
    {
        return $query->onlyTrashed()->orWhere('status', 'Archived');
    }

    public static function idTypeConfigs(): array
    {
        return [
            'PhilSys National ID (PCN)' => [
                'placeholder' => '1234-5678-9012-3456',
                'helper' => '16-digit PhilSys Card Number (PCN) printed on the card face',
                'maxlength' => 19,
                'pattern' => '^(\\d{4}-\\d{4}-\\d{4}(-\\d{4})?|\\d{12,16})$',
            ],
            'Driver\'s License' => [
                'placeholder' => 'N01-23-456789',
                'helper' => '1 letter, 2 digits, 2 digits, 6 digits (e.g., N01-23-456789)',
                'maxlength' => 13,
                'pattern' => '^([A-Z]\\d{2}-\\d{2}-\\d{6}|[A-Z]\\d{10}|[A-Z0-9-]{8,15})$',
            ],
            'UMID' => [
                'placeholder' => '1234-5678901-2 or CRN-12345678',
                'helper' => '12-digit Common Reference Number (CRN) or UMID card number',
                'maxlength' => 16,
                'pattern' => '(^CRN-?\\d{7,12}$)|(^\\d{4}-\\d{7}-\\d{1}$)|(^\\d{12}$)',
            ],
            'Passport' => [
                'placeholder' => 'P1234567 or P1234567A',
                'helper' => 'DFA Passport number (e.g., P1234567 or P1234567A)',
                'maxlength' => 12,
                'pattern' => '^[A-Z][0-9]{7,8}[A-Z]?$',
            ],
            'PRC ID' => [
                'placeholder' => '1234567',
                'helper' => '7-digit Professional Regulation Commission registration number',
                'maxlength' => 7,
                'pattern' => '^\\d{7}$',
            ],
            'Postal ID' => [
                'placeholder' => '123456789012',
                'helper' => 'Postal Identification / PRN card number',
                'maxlength' => 20,
                'pattern' => '^[A-Z0-9 -]{9,20}$',
            ],
            'Voter\'s ID' => [
                'placeholder' => '1234-5678A-B1234CDE',
                'helper' => 'Voter Identification Number (VIN) or certification number',
                'maxlength' => 30,
                'pattern' => '^[A-Z0-9 -]{6,30}$',
            ],
            'Other' => [
                'placeholder' => 'Enter official ID number',
                'helper' => 'Official government, company, or institutional ID number',
                'maxlength' => 50,
                'pattern' => '^[A-Z0-9\\s\\/-]{3,50}$',
            ],
        ];
    }

    public static function validateIdFormat(?string $type, ?string $number): bool
    {
        if (! $type || ! $number) {
            return false;
        }

        $configs = self::idTypeConfigs();
        $pattern = $configs[$type]['pattern'] ?? '^[A-Z0-9\\s\\/-]{3,50}$';

        return (bool) preg_match('/'.$pattern.'/i', trim($number));
    }
}
