<?php

namespace App\Policies;

use App\Models\TdsDeduction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TdsDeductionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->hasSettingsAccess($user);
    }

    public function view(User $user, TdsDeduction $tdsDeduction): bool
    {
        return $this->hasSettingsAccess($user) && $this->belongsToTenant($tdsDeduction);
    }

    public function create(User $user): bool
    {
        return $this->hasSettingsAccess($user);
    }

    public function update(User $user, TdsDeduction $tdsDeduction): bool
    {
        return $this->hasSettingsAccess($user) && $this->belongsToTenant($tdsDeduction);
    }

    public function delete(User $user, TdsDeduction $tdsDeduction): bool
    {
        return $this->hasSettingsAccess($user) && $this->belongsToTenant($tdsDeduction);
    }

    private function hasSettingsAccess(User $user): bool
    {
        return $user->hasPermission('settings.manage');
    }

    private function belongsToTenant(TdsDeduction $tdsDeduction): bool
    {
        $instituteId = \App\Support\TenantContext::id();

        return $instituteId && $tdsDeduction->institute_id === $instituteId;
    }
}
