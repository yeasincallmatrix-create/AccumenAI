<?php

namespace App\Services;

use App\Models\User;

class UserAccountService
{
    /** Normal self-registration → Owner Account. */
    public function registerOwner(array $data): User
    {
        return User::create($data + ['account_type' => 'owner']);
    }

    /** Staff invitation/onboarding → Staff Account. Never merges or converts. */
    public function createStaffFromInvitation(array $data): User
    {
        return User::create($data + ['account_type' => 'staff']);
    }
}
