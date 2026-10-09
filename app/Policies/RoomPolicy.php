<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

/**
 * Access policy for Room entities according to Narciana TRMS RBAC specifications.
 */
class RoomPolicy
{
    /** Every authenticated staff role can view room availability. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner', 'Manager', 'Caretaker');
    }

    /** Every authenticated staff role can view room details. */
    public function view(User $user, Room $room): bool
    {
        return $user->hasRole('Owner', 'Manager', 'Caretaker');
    }

    /** Only the Owner can create new rooms. */
    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    /** Only the Owner can update room configurations. */
    public function update(User $user, Room $room): bool
    {
        return $user->isOwner();
    }

    /** Only the Owner can adjust base monthly rents. */
    public function updateRent(User $user, Room $room): bool
    {
        return $user->isOwner();
    }

    /** Only the Owner can delete rooms. */
    public function delete(User $user, Room $room): bool
    {
        return $user->isOwner();
    }

    /**
     * Owner and Manager can encode an MOA, strictly on a Vacant unit.
     * Caretaker is strictly forbidden.
     */
    public function encodeMoa(User $user, Room $room): bool
    {
        return $user->hasRole('Owner', 'Manager') && $room->status === 'Vacant';
    }
}
