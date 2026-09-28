<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tenant profile. */
class Tenant extends Model
{
    protected $table = 'tbl_tenant';

    protected $primaryKey = 'tenant_id';

    public $timestamps = false;

    protected $fillable = [
        'full_name',
        'contact_no',
        'valid_id_type',
        'valid_id_no',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_id');
    }
}
