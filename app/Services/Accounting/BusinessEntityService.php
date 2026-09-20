<?php

namespace App\Services\Accounting;

use App\Enums\BusinessEntityType;
use App\Models\Institute;

class BusinessEntityService
{
    public function getType(int $instituteId): BusinessEntityType
    {
        $value = Institute::where('id', $instituteId)->value('business_entity_type');

        return ($value ? BusinessEntityType::tryFrom($value) : null)
            ?? BusinessEntityType::SINGLE_ENTITY;
    }

    public function setType(int $instituteId, BusinessEntityType $type): void
    {
        Institute::where('id', $instituteId)
            ->update(['business_entity_type' => $type->value]);
    }

    /**
     * Options available to a tenant: full list when advanced mode is ON,
     * single-entity only otherwise.
     */
    public function availableOptions(int $instituteId): array
    {
        $advanced = app(TenantAccountingModeService::class)->isAdvancedEnabled($instituteId);

        if (! $advanced) {
            return [BusinessEntityType::SINGLE_ENTITY->value => BusinessEntityType::SINGLE_ENTITY->label()];
        }

        return BusinessEntityType::options();
    }

    /**
     * Reset to single_entity (called when advanced mode is turned OFF).
     */
    public function resetToSingleEntity(int $instituteId): void
    {
        Institute::where('id', $instituteId)
            ->update(['business_entity_type' => BusinessEntityType::SINGLE_ENTITY->value]);
    }

    /**
     * Suggested COA accounts for an entity type.
     *
     * DISPLAY-ONLY: callers must never auto-create these rows.
     */
    public function suggestedAccounts(BusinessEntityType $type): array
    {
        return match ($type) {
            BusinessEntityType::SINGLE_ENTITY => [],
            BusinessEntityType::SOLE_PROPRIETORSHIP => [
                ['code' => '3001', 'name' => "Owner's Capital", 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3003', 'name' => "Owner's Drawings", 'category' => 'equity', 'type' => 'equity'],
            ],
            BusinessEntityType::PARTNERSHIP => [
                ['code' => '3010', 'name' => 'Partners Capital (Parent)', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3020', 'name' => 'Partners Drawings (Parent)', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3030', 'name' => 'Partners Loan', 'category' => 'liability', 'type' => 'non_current_liability'],
            ],
            BusinessEntityType::PRIVATE_LIMITED => [
                ['code' => '3101', 'name' => 'Share Capital', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3102', 'name' => 'Share Premium', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3103', 'name' => 'Retained Earnings', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3201', 'name' => 'Dividend Declared', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '2201', 'name' => 'Dividend Payable', 'category' => 'liability', 'type' => 'current_liability'],
            ],
        };
    }
}
