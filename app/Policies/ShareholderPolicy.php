<?php

namespace App\Policies;

use App\Models\Shareholder;

class ShareholderPolicy
{
    public function viewAny($user): bool
    {
        return tenant_id() !== null;
    }

    public function view($user, Shareholder $s): bool
    {
        return (int) $s->institute_id === tenant_id();
    }

    public function create($user): bool
    {
        return tenant_id() !== null;
    }

    public function update($user, Shareholder $s): bool
    {
        return (int) $s->institute_id === tenant_id();
    }

    public function delete($user, Shareholder $s): bool
    {
        return (int) $s->institute_id === tenant_id();
    }
}
