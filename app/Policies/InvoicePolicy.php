<?php

namespace App\Policies;

use App\Models\Invoice;

class InvoicePolicy
{
    /**
     * NOTE: $user intentionally untyped (institute_user guard).
     */
    public function viewAny($user): bool
    {
        return tenant_id() !== null;
    }

    public function view($user, Invoice $invoice): bool
    {
        return (int) $invoice->institute_id === tenant_id();
    }

    public function create($user): bool
    {
        return tenant_id() !== null;
    }

    public function update($user, Invoice $invoice): bool
    {
        return (int) $invoice->institute_id === tenant_id()
            && ! in_array($invoice->status, ['paid', 'cancelled']);
    }

    public function delete($user, Invoice $invoice): bool
    {
        return (int) $invoice->institute_id === tenant_id()
            && $invoice->status !== 'paid';
    }

    public function cancel($user, Invoice $invoice): bool
    {
        return (int) $invoice->institute_id === tenant_id()
            && ! in_array($invoice->status, ['paid', 'cancelled']);
    }
}
