<?php

namespace App\Policies;

use App\Models\Payment;

class PaymentPolicy
{
    /**
     * NOTE: $user intentionally untyped (institute_user guard).
     * Payments carry no status column — reversal idempotency lives
     * in PaymentService (journal reverse); policy guards tenancy.
     */
    public function viewAny($user): bool
    {
        return tenant_id() !== null;
    }

    public function view($user, Payment $payment): bool
    {
        return (int) $payment->institute_id === tenant_id();
    }

    public function create($user): bool
    {
        return tenant_id() !== null;
    }

    public function update($user, Payment $payment): bool
    {
        return (int) $payment->institute_id === tenant_id();
    }

    public function delete($user, Payment $payment): bool
    {
        return (int) $payment->institute_id === tenant_id();
    }

    public function reverse($user, Payment $payment): bool
    {
        return (int) $payment->institute_id === tenant_id();
    }
}
