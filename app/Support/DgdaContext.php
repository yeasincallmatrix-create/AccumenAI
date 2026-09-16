<?php

namespace App\Support;

use App\Models\InstituteSetting;
use App\Models\Setting;
use App\Services\Medical\DgdaService;

class DgdaContext
{
    public static function isMasterEnabled(): bool
    {
        return DgdaService::enabled();
    }

    public static function isTenantEnabled(?int $instituteId = null): bool
    {
        $instituteId = $instituteId ?? MedicalScope::instituteId();
        if (! $instituteId) {
            return false;
        }

        $settings = InstituteSetting::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->first(['dgda_enabled']);

        return (bool) ($settings?->dgda_enabled ?? false);
    }

    public static function isEnabled(?int $instituteId = null): bool
    {
        return self::isMasterEnabled() && self::isTenantEnabled($instituteId);
    }

    public static function mode(?int $instituteId = null): string
    {
        return self::isEnabled($instituteId) ? 'hybrid' : 'general';
    }
}
