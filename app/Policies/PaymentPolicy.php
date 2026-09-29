<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;

class PaymentPolicy
{
    /** Only the Owner voids a payment, and only that of the newest paid bill of the lease (decision 14). */
    public function void(User $user, Payment $payment): bool
    {
        return $user->hasRole('Owner') && app(PaymentService::class)->canVoid($payment);
    }
}
