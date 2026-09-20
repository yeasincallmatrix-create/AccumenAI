<?php

namespace App\Policies;

use App\Models\ShareCapitalTransaction;

class ShareCapitalTransactionPolicy
{
    public function viewAny($user): bool
    {
        return tenant_id() !== null;
    }

    public function create($user): bool
    {
        return tenant_id() !== null;
    }

    public function view($user, ShareCapitalTransaction $t): bool
    {
        return (int) $t->institute_id === tenant_id();
    }
}
