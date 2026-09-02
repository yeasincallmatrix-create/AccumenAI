<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Active workspace (organization) resolution.
 *
 * The currently authenticated global user may hold many memberships. The
 * active organization is stored in the session and is always re-verified
 * against the user's memberships on every request by the middleware.
 *
 * Never trust an organization id coming from the browser — always resolve
 * and verify through here.
 */
final class Workspace
{
    public const SESSION_KEY = 'active_institution_id';

    public static function set(?int $institutionId): void
    {
        session([self::SESSION_KEY => $institutionId]);
        TenantContext::set($institutionId);

        // Keep the branch scope in sync with the active membership so the
        // tenant/branch pair is consistent within the same request (the
        // SetTenantContext middleware re-applies it on the next request).
        $membership = $institutionId !== null ? self::membership() : null;
        BranchContext::set($membership?->branch_id);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        TenantContext::clear();
        BranchContext::clear();
    }

    public static function id(): ?int
    {
        return session(self::SESSION_KEY);
    }

    /**
     * The active membership for the current user, verified against their
     * memberships. Returns null when no membership is active/valid.
     */
    public static function membership(): ?Membership
    {
        $user = auth()->user();

        if ($user instanceof PlatformAdmin) {
            return null;
        }

        if (! $user instanceof User) {
            return null;
        }

        $id = self::id();
        if ($id === null) {
            return null;
        }

        $membership = Membership::query()
            ->where('user_id', $user->id)
            ->where('institution_id', $id)
            ->where('status', 'active')
            ->first();

        if ($membership === null || ! $membership->roleAllowedForAccountType($user)) {
            return null;
        }

        return $membership;
    }

    /**
     * Verification used by the middleware: if a session workspace id exists,
     * it must be a real, active membership — consistent with the user's
     * account type — or the request is rejected.
     */
    public static function verify(?int $institutionId, int $userId): bool
    {
        if ($institutionId === null) {
            return false;
        }

        $membership = Membership::query()
            ->where('user_id', $userId)
            ->where('institution_id', $institutionId)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            return false;
        }

        return $membership->roleAllowedForAccountType(User::query()->find($userId));
    }

    /**
     * Normalize the workspace after login:
     *   - 0 memberships     -> null (user must create/join an organization)
     *   - 1 membership      -> auto-activate it (skip picker)
     *   - N memberships     -> accent an explicit choice if valid, else null
     *                          (forces the workspace picker)
     */
    public static function resolveAfterLogin(User $user, ?int $requestedId = null): ?int
    {
        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->filter(fn (Membership $membership) => $membership->roleAllowedForAccountType($user));

        if ($memberships->isEmpty()) {
            return null;
        }

        if ($memberships->count() === 1) {
            return $memberships->first()->institution_id;
        }

        if ($requestedId !== null) {
            $match = $memberships->firstWhere('institution_id', $requestedId);
            if ($match !== null) {
                return $match->institution_id;
            }
        }

        return null;
    }
}
