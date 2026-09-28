<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/** System user: Owner, Manager or Caretaker. */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public const ROLES = ['Owner', 'Manager', 'Caretaker'];

    protected $table = 'tbl_user';

    protected $primaryKey = 'user_id';

    public $timestamps = false;

    protected $fillable = [
        'full_name',
        'username',
        'password_hash',
        'role',
        'contact_no',
        'is_active',
        'created_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            // Assigning a plain password here stores it bcrypt-hashed.
            'password_hash' => 'hashed',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /** Tell Laravel's login which column holds the password hash. */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /** tbl_user has no remember_token column, so "remember me" is disabled. */
    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
