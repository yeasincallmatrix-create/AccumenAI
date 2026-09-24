<?php

namespace App\Services;

use App\Models\Institute;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SuperAdminOverrideService
{
    /**
     * Create an emergency override (Super Admin only — caller must enforce 2FA).
     *
     * @param  string  $layer  'industry' or 'country'
     * @return int Inserted override ID
     */
    public function createOverride(
        Institute $institute,
        string $moduleKey,
        string $layer,
        string $reason,
        int $expiryDays = 7
    ): int {
        if (! in_array($layer, ['industry', 'country'], true)) {
            throw new InvalidArgumentException("Invalid layer: {$layer}");
        }

        return DB::table('super_admin_overrides')->insertGetId([
            'institute_id' => $institute->id,
            'module_key' => $moduleKey,
            'override_layer' => $layer,
            'reason' => $reason,
            'approved_by' => auth()->id() ?? 1,
            'two_factor_verified' => true,
            'started_at' => now(),
            'expires_at' => now()->addDays($expiryDays),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Check if an active (non-expired) override exists for the layer.
     */
    public function isActive(Institute $institute, string $moduleKey, string $layer): bool
    {
        return DB::table('super_admin_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', $moduleKey)
            ->where('override_layer', $layer)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Revoke an override by setting expires_at to now.
     */
    public function revokeOverride(int $overrideId): void
    {
        DB::table('super_admin_overrides')
            ->where('id', $overrideId)
            ->update([
                'expires_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
