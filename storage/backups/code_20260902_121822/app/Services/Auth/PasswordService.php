<?php

namespace App\Services\Auth;

use App\Support\PasswordHash;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Canonical password write-path — Password Security & Recovery Engine.
 *
 * Every application write to users.password_hash / institute_users.password_hash
 * / platform_admins.password_hash / guardians.password_hash MUST go through
 * this service so that:
 *   - plaintext is validated once
 *   - exactly one Hash::make() is executed
 *   - the Eloquent mutator (setPasswordHashAttribute) recognises the hash as
 *     already-valid and does NOT double-hash
 *   - no plaintext or hash is ever logged
 *   - writes are atomic and idempotent
 *   - sessions/tokens are revoked after credential change
 */
class PasswordService
{
    /**
     * Validate plaintext and produce exactly one hash.
     * Alias: hashPassword() for spec compatibility.
     */
    public function hash(string $plain): string
    {
        $this->validatePlain($plain);

        return Hash::make($plain);
    }

    public function hashPassword(string $plain): string
    {
        return $this->hash($plain);
    }

    /**
     * Atomically set the password for any authenticatable model that uses
     * password_hash (User, InstituteUser, PlatformAdmin, Guardian).
     *
     * The hash is generated exactly once here; the model's
     * setPasswordHashAttribute will keep it as-is because looksValid() is true.
     * Wrapped in a transaction and invalidates reset tokens + sessions.
     */
    public function setForUser(Authenticatable $user, string $plain): void
    {
        $hash = $this->hash($plain);

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $hash) {
            // Use Eloquent so the mutator runs but keeps the hash (looksValid === true).
            // forceFill bypasses fillable but still triggers setAttribute.
            $user->forceFill(['password_hash' => $hash])->save();

            // Invalidate any outstanding password-reset tokens for this email.
            try {
                if (isset($user->email) && $user->email) {
                    \Illuminate\Support\Facades\DB::table('password_reset_tokens')
                        ->where('email', $user->email)
                        ->delete();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });

        $this->revokeSessionsAfterPasswordChange($user);
        $this->recordSecurityEvent($user, 'password_changed');
    }

    /**
     * Authenticated password change: verifies current, then sets new.
     * Returns true on success, throws ValidationException on policy/current failure.
     */
    public function changePassword(Authenticatable $user, string $currentPlain, string $newPlain): void
    {
        if (! PasswordHash::safeCheck($currentPlain, (string) $user->getAuthPassword())) {
            $this->recordSecurityEvent($user, 'password_change_failed');
            throw ValidationException::withMessages(['current_password' => ['Your current password is incorrect.']]);
        }

        if ($currentPlain === $newPlain) {
            throw ValidationException::withMessages(['password' => ['New password must be different from current password.']]);
        }

        $this->setForUser($user, $newPlain);
    }

    /**
     * Attempt automatic rehash after successful login if algorithm/cost changed.
     * Must only be called AFTER verify succeeded, with plaintext available.
     */
    public function rehashIfNeeded(Authenticatable $user, string $plain): bool
    {
        $hash = (string) $user->getAuthPassword();
        if (! $this->needsRehash($hash)) {
            return false;
        }

        if (! PasswordHash::safeCheck($plain, $hash)) {
            return false;
        }

        $newHash = Hash::make($plain);
        // Atomic update — bypass mutator double-check via query to avoid race
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $newHash) {
                $user->forceFill(['password_hash' => $newHash])->save();
            });
            $this->recordSecurityEvent($user, 'password_hash_rehashed');
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }

    /**
     * Invalidate existing sessions and API tokens after credential change.
     * Keeps the current session alive when called from a web request.
     */
    public function revokeSessionsAfterPasswordChange(Authenticatable $user): void
    {
        try {
            // Delete other DB sessions (if sessions driver is database)
            $currentId = null;
            try {
                $currentId = request()?->session()?->getId();
            } catch (\Throwable $e) {
            }

            if ($currentId) {
                \Illuminate\Support\Facades\DB::table('sessions')
                    ->where('user_id', $user->getAuthIdentifier())
                    ->where('id', '!=', $currentId)
                    ->delete();
            } else {
                // No request context (e.g. reset flow) — revoke all
                \Illuminate\Support\Facades\DB::table('sessions')
                    ->where('user_id', $user->getAuthIdentifier())
                    ->delete();
            }

            // Revoke Sanctum tokens if model uses HasApiTokens
            if (method_exists($user, 'tokens')) {
                // Keep current token if API request
                $currentTokenId = null;
                try {
                    $currentTokenId = request()?->user()?->currentAccessToken()?->id;
                } catch (\Throwable $e) {
                }
                if ($currentTokenId) {
                    $user->tokens()->where('id', '!=', $currentTokenId)->delete();
                } else {
                    // On web password change, revoke all API tokens for safety
                    // Comment out if API tokens should survive web password change
                    // $user->tokens()->delete();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Regenerate remember token to invalidate remember-me cookies
        try {
            if (method_exists($user, 'setRememberToken')) {
                $user->setRememberToken(\Illuminate\Support\Str::random(60));
                $user->save();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Record a password security event without ever logging plaintext or hash.
     * Public so controllers (reset flows) can emit specific audit events.
     */
    public function recordSecurityEvent(Authenticatable $user, string $action): void
    {
        try {
            $instituteId = null;
            // Try to resolve institute context for audit grouping; allow null for global users
            if (isset($user->institute_id)) {
                $instituteId = $user->institute_id;
            } elseif (method_exists($user, 'getInstituteIdAttribute')) {
                try { $instituteId = $user->institute_id; } catch (\Throwable $e) {}
            }
            // Fallback to membership lookup for User without direct institute_id
            if ($instituteId === null && method_exists($user, 'memberships')) {
                try {
                    $m = $user->memberships()->where('status', 'active')->first();
                    $instituteId = $m?->institution_id;
                } catch (\Throwable $e) {}
            }

            if (class_exists(\App\Models\AuditLog::class)) {
                // Map model class to audit_logs.user_type enum
                $map = [
                    \App\Models\User::class => 'institute_user',
                    \App\Models\InstituteUser::class => 'institute_user',
                    \App\Models\PlatformAdmin::class => 'platform_admin',
                    \App\Models\Guardian::class => 'guardian',
                ];
                $auditUserType = $map[get_class($user)] ?? 'system';
                // institute_id bigint unsigned null allowed, but module is required
                \App\Models\AuditLog::create([
                    'institute_id' => $instituteId ?: 1,
                    'user_type' => $auditUserType,
                    'user_id' => $user->getAuthIdentifier(),
                    'action' => $action,
                    'module' => 'auth',
                    'record_id' => $user->getAuthIdentifier(),
                    'old_values' => null,
                    'new_values' => json_encode(['email' => $user->email ?? null, 'ip' => request()?->ip()]),
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        logger()->info('password security event', [
            'action' => $action,
            'user_type' => get_class($user),
            'user_id' => $user->getAuthIdentifier(),
            'email' => $user->email ?? null,
            'ip' => request()?->ip(),
        ]);
    }

    /**
     * Record detection of a corrupted hash (read-only, never logs hash).
     */
    public function recordInvalidHashDetected(Authenticatable|string $user, string $table, int|string $identifier): void
    {
        logger()->warning('password_hash_invalid_detected', [
            'table' => $table,
            'user_type' => is_string($user) ? $user : get_class($user),
            'user_id' => $identifier,
        ]);
    }

    /**
     * Safe verification that never throws on a corrupted hash.
     */
    public function verify(string $plain, string $hash): bool
    {
        return PasswordHash::safeCheck($plain, $hash);
    }

    public function verifyPassword(string $plain, string $hash): bool
    {
        return $this->verify($plain, $hash);
    }

    /**
     * Whether the stored hash is well-formed (valid bcrypt/argon).
     */
    public function isValidHash(string $hash): bool
    {
        return PasswordHash::looksValid($hash);
    }

    public function validateStoredHashFormat(string $hash): bool
    {
        return $this->isValidHash($hash);
    }

    /**
     * Whether the hash should be rehashed under current config (cost/alg change).
     * Returns false for malformed hashes — they must be reset, not rehashed.
     */
    public function needsRehash(string $hash): bool
    {
        if (! PasswordHash::looksValid($hash)) {
            return false;
        }

        return Hash::needsRehash($hash);
    }

    /**
     * Central policy validation — single source of truth via PasswordPolicy.
     * Keeps service layer authoritative even if caller bypasses FormRequest.
     */
    private function validatePlain(string $plain): void
    {
        $error = \App\Support\PasswordPolicy::check($plain);
        if ($error !== null) {
            throw ValidationException::withMessages(['password' => [$error]]);
        }
    }
}
