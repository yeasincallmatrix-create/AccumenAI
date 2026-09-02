<?php

namespace App\Services;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Models\UserModuleAccess;
use Illuminate\Support\Facades\Cache;

class UserModuleAccessService
{
    protected string $cachePrefix = 'user_module_access:';

    /**
     * Check if a module is enabled for a specific user within an institute.
     * Default is package/industry via ModuleAccessService, but per-user row overrides it (like AI).
     * If per-user row exists, its enabled value wins even over institute (admin can grant HR to FREE user or hide HR from PREMIUM user).
     * If no per-user row, falls back to institute-level.
     */
    public function isEnabledForUser(?Institute $institute, $user, string $moduleKey): bool
    {
        if (! $institute || ! $user) {
            return false;
        }

        $userType = $this->resolveUserType($user);
        $userId = $user->getKey();

        if (! $userType || ! $userId) {
            return app(ModuleAccessService::class)->isEnabled($institute, $moduleKey);
        }

        // Institute level must have it first — per-user can only revoke (like AI), not grant
        $instituteHas = app(ModuleAccessService::class)->isEnabled($institute, $moduleKey);
        if (! $instituteHas) {
            return false;
        }

        $cacheKey = $this->cachePrefix . $institute->id . ':' . $userType . ':' . $userId;

        $map = Cache::remember($cacheKey, 600, function () use ($institute, $userType, $userId) {
            return UserModuleAccess::where('institute_id', $institute->id)
                ->where('user_type', $userType)
                ->where('user_id', $userId)
                ->pluck('enabled', 'module_key')
                ->toArray();
        });

        // No per-user override → default visible (institute has it)
        if (! array_key_exists($moduleKey, $map)) {
            return true;
        }

        // Per-user override wins (admin can revoke for specific users)
        return (bool) $map[$moduleKey];
    }

    public function setAccess(Institute $institute, $user, string $moduleKey, bool $enabled, ?int $actorId = null, ?string $reason = null): UserModuleAccess
    {
        $userType = $this->resolveUserType($user);
        $userId = $user->getKey();

        $record = UserModuleAccess::updateOrCreate(
            [
                'institute_id' => $institute->id,
                'user_type' => $userType,
                'user_id' => $userId,
                'module_key' => $moduleKey,
            ],
            [
                'enabled' => $enabled,
                'created_by' => $actorId,
                'reason' => $reason,
            ]
        );

        $this->flushCache($institute->id, $userType, $userId);

        return $record;
    }

    public function removeAccess(Institute $institute, $user, string $moduleKey): void
    {
        $userType = $this->resolveUserType($user);
        $userId = $user->getKey();

        UserModuleAccess::where('institute_id', $institute->id)
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('module_key', $moduleKey)
            ->delete();

        $this->flushCache($institute->id, $userType, $userId);
    }

    public function getForUser(Institute $institute, $user): array
    {
        $userType = $this->resolveUserType($user);
        $userId = $user->getKey();

        return UserModuleAccess::where('institute_id', $institute->id)
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->pluck('enabled', 'module_key')
            ->toArray();
    }

    public function flushCache(int $instituteId, ?string $userType = null, ?int $userId = null): void
    {
        if ($userType && $userId) {
            Cache::forget($this->cachePrefix . $instituteId . ':' . $userType . ':' . $userId);
        } else {
            // Flush all for institute — iterate known keys is heavy, so just forget pattern via clearing matching keys if using array cache
            // For file/array cache, we need to forget each known user; simpler: flush all with prefix by scanning cache is not reliable, so we clear all user_module_access keys via Cache::flush is too broad
            // Instead, we will forget via direct DB of user ids for institute
            $keys = UserModuleAccess::where('institute_id', $instituteId)->get(['user_type', 'user_id']);
            foreach ($keys as $k) {
                Cache::forget($this->cachePrefix . $instituteId . ':' . $k->user_type . ':' . $k->user_id);
            }
        }
    }

    public function getAllModuleKeys(): array
    {
        return \App\Models\ModuleRegistry::pluck('key')->toArray();
    }

    public function resolveUserType($user): ?string
    {
        if ($user instanceof InstituteUser) {
            return 'institute_user';
        }
        if ($user instanceof User) {
            return 'user';
        }
        if ($user instanceof \App\Models\PlatformAdmin) {
            return null; // platform admin not institute-scoped
        }
        return null;
    }
}
