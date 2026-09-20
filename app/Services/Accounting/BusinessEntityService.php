<?php

namespace App\Services\Accounting;

use App\Enums\BusinessEntityType;
use App\Models\Institute;

class BusinessEntityService
{
    public function getType(int $instituteId): ?BusinessEntityType
    {
        $value = Institute::where('id', $instituteId)->value('business_entity_type');

        return $value ? BusinessEntityType::tryFrom($value) : null;
    }

    public function setType(int $instituteId, BusinessEntityType $type): void
    {
        Institute::where('id', $instituteId)
            ->update(['business_entity_type' => $type->value]);
    }

    /**
     * Suggested COA accounts for an entity type.
     *
     * DISPLAY-ONLY: callers must never auto-create these rows.
     */
    public function suggestedAccounts(BusinessEntityType $type): array
    {
        return match ($type) {
            BusinessEntityType::SOLE_PROPRIETORSHIP => [
                ['code' => '3001', 'name' => "Owner's Capital", 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3003', 'name' => "Owner's Drawings", 'category' => 'equity', 'type' => 'equity'],
            ],
            BusinessEntityType::PARTNERSHIP => [
                ['code' => '3010', 'name' => 'Partners Capital (Parent)', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3020', 'name' => 'Partners Drawings (Parent)', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3030', 'name' => 'Partners Loan', 'category' => 'liability', 'type' => 'liability'],
            ],
            BusinessEntityType::PRIVATE_LIMITED => [
                ['code' => '3101', 'name' => 'Share Capital', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3102', 'name' => 'Share Premium', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3103', 'name' => 'Retained Earnings', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '3201', 'name' => 'Dividend Declared', 'category' => 'equity', 'type' => 'equity'],
                ['code' => '2201', 'name' => 'Dividend Payable', 'category' => 'liability', 'type' => 'liability'],
            ],
        };
    }
}
