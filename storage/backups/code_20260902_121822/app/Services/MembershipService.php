<?php

namespace App\Services;

use App\Exceptions\AccountTypeMismatchException;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MembershipService
{
    public function assign(User $user, int $institutionId, int $roleId, array $attributes = []): Membership
    {
        $this->assertRoleAllowed($user, $roleId);

        return Membership::create(array_merge([
            'user_id' => $user->id,
            'institution_id' => $institutionId,
            'role_id' => $roleId,
            'status' => 'active',
        ], $attributes));
    }

    public function changeRole(Membership $membership, int $roleId): Membership
    {
        $this->assertRoleAllowed($membership->user, $roleId);

        $membership->role_id = $roleId;
        $membership->save();

        return $membership;
    }

    /**
     * Remove memberships whose user or institute no longer exists.
     *
     * Returns the number of deleted rows.
     */
    public function cleanOrphaned(): int
    {
        $table = (new Membership)->getTable();

        $userIds = DB::table('users')->pluck('id');
        $instIds = DB::table('institutes')->pluck('id');

        return DB::table($table)
            ->whereNotIn('user_id', $userIds)
            ->orWhereNotIn('institution_id', $instIds)
            ->delete();
    }

    public function assertRoleAllowed(User $user, int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        $isOwnerRole = $role->slug === 'institute-owner';

        if ($isOwnerRole && ! $user->isOwnerAccount()) {
            throw AccountTypeMismatchException::staffCannotOwn();
        }
        if (! $isOwnerRole && ! $user->isStaffAccount()) {
            throw AccountTypeMismatchException::ownerCannotBeStaff();
        }
    }
}
