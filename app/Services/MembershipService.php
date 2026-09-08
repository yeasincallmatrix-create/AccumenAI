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
        $this->assertRoleInInstitute($roleId, $institutionId);

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
        $this->assertRoleInInstitute($roleId, (int) $membership->institution_id);

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

    /**
     * Block cross-tenant role assignment: a role carrying an institute_id may
     * only be attached inside that same institute. Global roles
     * (institute_id NULL) are allowed everywhere.
     */
    public function assertRoleInInstitute(int $roleId, int $institutionId): void
    {
        $role = Role::findOrFail($roleId);

        if ($role->institute_id !== null && (int) $role->institute_id !== $institutionId) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'role_id' => ['The selected role does not belong to this organization.'],
            ]);
        }
    }
}
