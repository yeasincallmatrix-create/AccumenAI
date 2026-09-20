<?php

namespace App\Policies;

use App\Models\ChartOfAccount;

class ChartOfAccountPolicy
{
    /**
     * Accepts any guard user (institute_user, web, ...). Tenant identity
     * comes from tenant_id(), not the user model.
     */
    public function viewAny(mixed $user): bool
    {
        return tenant_id() !== null;
    }

    public function view(mixed $user, ChartOfAccount $account): bool
    {
        // Can view: global OR own tenant's
        if ($account->isGlobal()) {
            return true;
        }

        return (int) $account->institute_id === (int) tenant_id();
    }

    public function create(mixed $user): bool
    {
        return tenant_id() !== null;
    }

    public function update(mixed $user, ChartOfAccount $account): bool
    {
        return $account->isEditableBy((int) tenant_id());
    }

    public function delete(mixed $user, ChartOfAccount $account): bool
    {
        return $account->isEditableBy((int) tenant_id());
    }
}
