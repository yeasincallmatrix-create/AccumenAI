<?php

namespace App\Services\Inventory;

use App\Models\Institute;
use App\Services\Accounting\AccountingSetupService;
use Illuminate\Validation\ValidationException;

/**
 * Capability-based inventory configuration.
 *
 * Defaults come from config/industry_rules.php `capabilities` keyed by the
 * institute's industry; a tenant can override any capability at runtime via the
 * existing accounting-settings mechanism (settings key
 * `inventory.capability.<name>` scoped to institute+branch). A capability is
 * ON when the tenant override is set, otherwise the industry default.
 *
 * Capabilities enable/disable generic engine features — they never change
 * accounting logic.
 */
class InventoryCapabilityService
{
    public function __construct(
        private readonly AccountingSetupService $settings,
    ) {}

    public const DEFAULT_CAPABILITIES = [
        'inventory.enabled',
        'inventory.multi_warehouse',
        'inventory.batch_tracking',
        'inventory.expiry_tracking',
        'inventory.serial_tracking',
        'inventory.barcode',
        'inventory.stock_transfer',
        'inventory.stock_adjustment',
        'inventory.stock_count',
        'inventory.purchase_receipt',
        'inventory.sales_issue',
        'inventory.stock_return',
        'inventory.wastage',
        'inventory.bom',
        'inventory.recipe',
        'inventory.lot_tracking',
        'inventory.consumption',
        'inventory.production',
    ];

    public function industryDefaults(string $industry): array
    {
        $capabilities = config('industry_rules.capabilities');

        return array_merge(
            array_fill_keys(self::DEFAULT_CAPABILITIES, false),
            $capabilities[$industry] ?? [],
        );
    }

    /**
     * Whether a capability is enabled for the institute. The tenant override
     * (when present) wins over the industry default.
     */
    public function has(int $instituteId, string $capability, ?int $branchId = null): bool
    {
        $override = $this->settings->getSetting(
            $instituteId,
            'inventory.capability.'.$capability,
            null,
            $branchId,
        );

        if ($override !== null) {
            return (bool) $override;
        }

        $industry = (string) Institute::query()->whereKey($instituteId)->value('industry');

        return (bool) ($this->industryDefaults($industry)[$capability] ?? false);
    }

    /**
     * Enable or disable a capability for the tenant (persisted override).
     */
    public function set(int $instituteId, string $capability, bool $enabled, ?int $branchId = null, ?int $actorId = null): void
    {
        $this->settings->setSetting(
            $instituteId,
            'inventory.capability.'.$capability,
            $enabled,
            $branchId,
            $actorId,
        );
    }

    /**
     * Throw a ValidationException when the capability is disabled.
     */
    public function assert(int $instituteId, string $capability, ?int $branchId = null): void
    {
        if (! $this->has($instituteId, $capability, $branchId)) {
            throw ValidationException::withMessages([
                'capability' => mawa_lang('validation_services.inventory.capability_disabled', ['capability' => $capability]),
            ]);
        }
    }

    public function allFor(int $instituteId, ?int $branchId = null): array
    {
        $industry = (string) Institute::query()->whereKey($instituteId)->value('industry');
        $defaults = $this->industryDefaults($industry);

        $result = [];
        foreach ($defaults as $capability => $default) {
            $override = $this->settings->getSetting($instituteId, 'inventory.capability.'.$capability, null, $branchId);
            $result[$capability] = $override !== null ? (bool) $override : $default;
        }

        return $result;
    }
}
