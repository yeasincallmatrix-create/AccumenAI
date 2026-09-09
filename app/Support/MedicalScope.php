<?php

namespace App\Support;

use App\Models\InstituteUser;
use App\Models\User;

/**
 * Phase 1 — HMS Patient & OPD.
 *
 * Single place that resolves "the current medical institute id".
 *
 * The Phase 2.md spec assumed `auth()->user()->institute_id`, which only
 * exists for the `institute_user` guard. Web-guard users (global User +
 * membership) and tenant context carry the institute differently, so every
 * medical controller / request / service resolves through here instead:
 * TenantContext → Workspace → InstituteUser → active membership.
 */
final class MedicalScope
{
    public static function instituteId(): ?int
    {
        $tenantId = TenantContext::id();
        if ($tenantId) {
            return (int) $tenantId;
        }

        try {
            $workspaceId = Workspace::id();
        } catch (\Throwable) {
            $workspaceId = null;
        }
        if ($workspaceId) {
            return (int) $workspaceId;
        }

        try {
            $user = request()->user();
        } catch (\Throwable) {
            return null;
        }

        if ($user instanceof InstituteUser) {
            return $user->institute_id ? (int) $user->institute_id : null;
        }

        if ($user instanceof User) {
            try {
                $membership = Workspace::membership();
            } catch (\Throwable) {
                $membership = null;
            }
            if ($membership) {
                return (int) $membership->institution_id;
            }
        }

        return null;
    }

    public static function instituteIdOrFail(): int
    {
        $id = self::instituteId();

        if (! $id) {
            abort(403, 'No institute context.');
        }

        return $id;
    }

    /**
     * Phase 2 addition (additive only — existing methods untouched).
     *
     * Alias matching the Phase 2 spec's `MedicalScope::getInstituteId()`
     * naming so spec-derived code reads naturally.
     */
    public static function getInstituteId(): int
    {
        return self::instituteIdOrFail();
    }

    /**
     * Phase 2 addition.
     *
     * Scope a query to the current institute (mirrors the Phase 2 spec's
     * `MedicalScope::apply()`). Only use on models that carry institute_id;
     * VitalSign/NursingNote scope through their admission instead.
     */
    public static function apply($query)
    {
        return $query->where('institute_id', self::instituteIdOrFail());
    }

    /**
     * Phase 02 — user ids selectable as doctors in the given institute:
     * active global users holding an active membership here, or holding a
     * Doctor profile here. Dropdowns AND write validation share this single
     * definition, so the UI can never offer what the backend would reject.
     * Empty result fails closed (no selectable doctors, never everyone).
     */
    public static function instituteDoctorUserIds(int $instituteId): array
    {
        try {
            $memberIds = \App\Models\Membership::where('institution_id', $instituteId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->pluck('user_id');
            $profileIds = \App\Models\Medical\Doctor::where('institute_id', $instituteId)
                ->pluck('user_id');

            return $memberIds->merge($profileIds)->unique()->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Phase 02 — dropdown source for doctor selectors. Same ordering as the
     * previous global list, restricted to institute members/profile holders.
     */
    public static function instituteDoctors(int $instituteId)
    {
        return \App\Models\User::where('status', 'active')
            ->whereIn('id', static::instituteDoctorUserIds($instituteId))
            ->orderBy('name')
            ->get();
    }

    /**
     * Phase 02 — write-path check: the chosen doctor must belong to the
     * institute (membership or Doctor profile). Inactive users fail.
     */
    public static function isDoctorInInstitute(int $userId, int $instituteId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $user = \App\Models\User::whereKey($userId)->where('status', 'active')->first();
        if (! $user) {
            return false;
        }

        return in_array($userId, static::instituteDoctorUserIds($instituteId), true);
    }

    /**
     * users.id of the doctor the current user IS, for doctor data isolation.
     *
     * Returns null (no fencing) for owners, receptionists, nurses and anyone
     * without a linked Doctor profile — they keep their current visibility.
     * A linked non-owner doctor gets fenced to their own queue/patients/fees.
     */
    public static function ownDoctorUserId(?int $instituteId = null): ?int
    {
        try {
            $instituteId ??= self::instituteId();
            if (! $instituteId) {
                return null;
            }

            $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();
            if (! $staff) {
                return null;
            }

            // Owners see everything, even with a doctor profile.
            if ($staff instanceof InstituteUser && $staff->isOwner()) {
                return null;
            }
            if ($staff instanceof User && $staff->isOwner()) {
                return null;
            }

            if ($staff instanceof InstituteUser) {
                $email = $staff->email ?? null;
                if (! is_string($email) || $email === '') {
                    return null;
                }
                // Phase 02: prefer the stable in-module link (a Doctor
                // profile in this institute carrying the same email) over
                // the global-users hop, which breaks when either side's
                // email drifts. Legacy hop retained as fallback.
                $profile = \App\Models\Medical\Doctor::where('institute_id', (int) $instituteId)
                    ->where('email', $email)
                    ->first();
                if (! $profile) {
                    $user = User::where('email', $email)->first();
                    if (! $user) {
                        return null;
                    }
                    $profile = \App\Models\Medical\Doctor::resolveForUser((int) $user->id, (int) $instituteId);
                }
            } elseif ($staff instanceof User) {
                $profile = \App\Models\Medical\Doctor::resolveForUser((int) $staff->id, (int) $instituteId);
            } else {
                return null;
            }

            return $profile ? (int) $profile->user_id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Phase 2 addition.
     *
     * Resolve a `users.id` for audit columns (vitals.recorded_by,
     * notes.recorded_by, admissions.discharged_by) whose FK points at the
     * global users table. Web-guard users map directly; institute_user-guard
     * staff have no users row, so null is returned and the nullable column
     * stays empty instead of violating the FK.
     */
    public static function recorderId(): ?int
    {
        try {
            $user = request()->user();
        } catch (\Throwable) {
            return null;
        }

        if ($user instanceof User) {
            return (int) $user->getKey();
        }

        return null;
    }
}
