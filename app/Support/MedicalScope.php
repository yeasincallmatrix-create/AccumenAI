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
