<?php

namespace App\Policies;

use App\Models\Partner;

class PartnerPolicy
{
    public function viewAny($user): bool
    {
        return tenant_id() !== null;
    }

    public function view($user, Partner $p): bool
    {
        return (int) $p->institute_id === tenant_id();
    }

    public function create($user): bool
    {
        return tenant_id() !== null;
    }

    public function update($user, Partner $p): bool
    {
        return (int) $p->institute_id === tenant_id();
    }

    public function delete($user, Partner $p): bool
    {
        return (int) $p->institute_id === tenant_id();
    }
}
