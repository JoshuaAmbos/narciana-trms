<?php

namespace App\Policies;

use App\Models\User;

/**
 * Record-level rules for user accounts. The 'role:Owner' middleware already limits the pages to the Owner;
 * these rules stop the Owner from locking themselves out (decision 9).
 */
class UserPolicy
{
    public function deactivate(User $actor, User $target): bool
    {
        return $actor->user_id !== $target->user_id && $target->is_active;
    }

    public function activate(User $actor, User $target): bool
    {
        return ! $target->is_active;
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $actor->user_id !== $target->user_id;
    }
}
