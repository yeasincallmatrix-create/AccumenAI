<?php

namespace App\Services\FixedAsset;

use App\Models\Institute;
use App\Services\Accounting\AccountingSetupService;
use Illuminate\Validation\ValidationException;

/**
 * Capability-based fixed-asset configuration (STEP 17).
 *
 * Defaults come from config/industry_rules.php `capabilities.<industry>.assets`
 * (a nested `assets.*` key per industry); a tenant can override any capability
 * via the accounting-settings mechanism (settings key
 * `assets.capability.<name>`). Revaluation and QR are OFF by default.
 */
class FixedAssetCapabilityService
{
    public function __construct(
        private readonly AccountingSetupService $settings,
    ) {}

    public const DEFAULT_CAPABILITIES = [
        'assets.enabled',
        'assets.depreciation',
        'assets.transfer',
        'assets.disposal',
        'assets.impairment',
        'assets.revaluation',
        'assets.qr',
        'assets.warranty',
    ];

    public function industryDefaults(string $industry): array
    {
        $caps = config('industry_rules.capabilities');

        $assets = $caps[$industry]['assets'] ?? [];

        return array_merge(array_fill_keys(self::DEFAULT_CAPABILITIES, false), $assets);
    }

    public function has(int $instituteId, string $capability, ?int $branchId = null): bool
    {
        $override = $this->settings->getSetting(
            $instituteId,
            'assets.capability.'.$capability,
            null,
            $branchId,
        );

        if ($override !== null) {
            return (bool) $override;
        }

        $industry = (string) Institute::query()->whereKey($instituteId)->value('industry');

        return (bool) ($this->industryDefaults($industry)[$capability] ?? false);
    }

    public function set(int $instituteId, string $capability, bool $enabled, ?int $branchId = null, ?int $actorId = null): void
    {
        $this->settings->setSetting($instituteId, 'assets.capability.'.$capability, $enabled, $branchId, $actorId);
    }

    public function assert(int $instituteId, string $capability, ?int $branchId = null): void
    {
        if (! $this->has($instituteId, $capability, $branchId)) {
            throw ValidationException::withMessages([
                'capability' => 'The fixed-asset capability "'.$capability.'" is not enabled for this institute.',
            ]);
        }
    }

    public function allFor(int $instituteId, ?int $branchId = null): array
    {
        $industry = (string) Institute::query()->whereKey($instituteId)->value('industry');
        $defaults = $this->industryDefaults($industry);

        $result = [];
        foreach ($defaults as $capability => $default) {
            $override = $this->settings->getSetting($instituteId, 'assets.capability.'.$capability, null, $branchId);
            $result[$capability] = $override !== null ? (bool) $override : $default;
        }

        return $result;
    }
}
