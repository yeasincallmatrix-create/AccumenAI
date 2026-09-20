<?php

namespace App\Policies;

use App\Models\Dividend;

class DividendPolicy
{
    public function viewAny($user): bool
    {
        return tenant_id() !== null;
    }

    public function view($user, Dividend $d): bool
    {
        return (int) $d->institute_id === tenant_id();
    }

    public function create($user): bool
    {
        return tenant_id() !== null;
    }

    public function update($user, Dividend $d): bool
    {
        // Draft (declare/mark) + declared (pay) are mutable; paid/cancelled locked.
        return (int) $d->institute_id === tenant_id()
            && in_array($d->status, ['draft', 'declared']);
    }

    public function delete($user, Dividend $d): bool
    {
        return (int) $d->institute_id === tenant_id() && $d->canCancel();
    }
}
