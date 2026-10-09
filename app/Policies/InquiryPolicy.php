<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;

/**
 * RBAC policy for Inquiry operations in Narciana TRMS.
 * All staff roles can log, view, and update inquiries.
 * Lease conversions (Encode MOA) are strictly reserved for Manager and Owner.
 */
class InquiryPolicy
{
    /** Every authenticated staff role can view inquiries. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Owner', 'Manager', 'Caretaker');
    }

    /** Every authenticated staff role can view individual inquiry details. */
    public function view(User $user, Inquiry $inquiry): bool
    {
        return $user->hasRole('Owner', 'Manager', 'Caretaker');
    }

    /** All staff roles can log new inquiries. */
    public function create(User $user): bool
    {
        return $user->hasRole('Owner', 'Manager', 'Caretaker');
    }

    /** All staff roles can update inquiry outcomes/remarks. */
    public function update(User $user, Inquiry $inquiry): bool
    {
        return $user->hasRole('Owner', 'Manager', 'Caretaker');
    }

    /**
     * Converting an inquiry into an active MOA tenancy.
     * Authorized strictly for Owner and Manager on Waiting inquiries.
     * Caretakers are strictly forbidden.
     */
    public function encodeMoa(User $user, Inquiry $inquiry): bool
    {
        return $user->hasRole('Owner', 'Manager') && $inquiry->status === 'Waiting';
    }

    /** Only the Owner can delete inquiry records if ever needed. */
    public function delete(User $user, Inquiry $inquiry): bool
    {
        return $user->isOwner();
    }
}
