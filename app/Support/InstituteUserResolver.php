<?php

namespace App\Support;

use App\Models\InstituteUser;
use App\Models\User;

/**
 * Resolve a mixed actor id to a valid institute_users id.
 *
 * Service callers may pass a global users.id (web guard, tests, controller
 * user()->id) while *_by FK columns (created_by, requested_by, resolved_by,
 * approver_id, matched_by) reference institute_users.id. Map via the
 * actor's email within the same institute when possible; otherwise NULL
 * (those columns are nullable with optional relations) instead of
 * violating the foreign key.
 */
final class InstituteUserResolver
{
    public static function resolve(int $instituteId, int $actorId): ?int
    {
        if (InstituteUser::whereKey($actorId)->exists()) {
            return $actorId;
        }

        $email = User::whereKey($actorId)->value('email');
        if (is_string($email) && $email !== '') {
            $id = InstituteUser::where('institute_id', $instituteId)
                ->where('email', $email)
                ->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        return null;
    }
}
