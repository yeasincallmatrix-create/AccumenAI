<?php

namespace App\Policies;

use App\Models\CorporateTaxComputation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CorporateTaxComputationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasSettingsAccess($user);
    }

    public function view(User $user, CorporateTaxComputation $computation): bool
    {
        return $this->hasSettingsAccess($user) && $this->belongsToTenant($computation);
    }

    public function create(User $user): bool
    {
        return $this->hasSettingsAccess($user);
    }

    public function update(User $user, CorporateTaxComputation $computation): bool
    {
        return $this->hasSettingsAccess($user) && $this->belongsToTenant($computation);
    }

    public function delete(User $user, CorporateTaxComputation $computation): bool
    {
        return $this->hasSettingsAccess($user) && $this->belongsToTenant($computation);
    }

    private function hasSettingsAccess(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }

    private function belongsToTenant(CorporateTaxComputation $computation): bool
    {
        $instituteId = \App\Support\TenantContext::id();

        return $instituteId && $computation->institute_id === $instituteId;
    }
}
