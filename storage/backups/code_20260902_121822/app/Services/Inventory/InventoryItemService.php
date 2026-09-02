<?php

namespace App\Services\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Master-data management for the inventory subsystem: categories, warehouses
 * and items. All records are tenant-scoped with the BranchScopedOrShared
 * convention (branch_id NULL = institute-wide) and ownership is validated on
 * every write so a tenant can never touch another tenant's data.
 */
class InventoryItemService
{
    public const ITEM_TYPES = [
        'stock_item',
        'consumable',
        'medicine',
        'raw_material',
        'finished_good',
        'spare_part',
        'service_consumable',
        'other',
    ];

    public function createCategory(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): InventoryCategory
    {
        $data = $this->validateCategory($instituteId, $branchId, $data);

        return InventoryCategory::create(array_merge($data, [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'created_by' => $actorId,
        ]));
    }

    public function updateCategory(InventoryCategory $category, int $instituteId, array $data, ?int $actorId = null): InventoryCategory
    {
        $this->assertOwned($category, $instituteId);
        $data = $this->validateCategory($instituteId, $category->branch_id, $data, exceptName: $category->name);

        $category->forceFill(array_merge($data, ['updated_by' => $actorId]))->save();

        return $category->fresh();
    }

    public function deleteCategory(InventoryCategory $category, int $instituteId): void
    {
        $this->assertOwned($category, $instituteId);

        if ($category->items()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This category still has items and cannot be deleted.',
            ]);
        }

        $category->delete();
    }

    public function createWarehouse(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): InventoryWarehouse
    {
        $data = $this->validateWarehouse($instituteId, $branchId, $data);

        return InventoryWarehouse::create(array_merge($data, [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'created_by' => $actorId,
        ]));
    }

    public function updateWarehouse(InventoryWarehouse $warehouse, int $instituteId, array $data, ?int $actorId = null): InventoryWarehouse
    {
        $this->assertOwned($warehouse, $instituteId);
        $data = $this->validateWarehouse($instituteId, $warehouse->branch_id, $data, exceptCode: $warehouse->code);

        $warehouse->forceFill(array_merge($data, ['updated_by' => $actorId]))->save();

        return $warehouse->fresh();
    }

    public function deleteWarehouse(InventoryWarehouse $warehouse, int $instituteId): void
    {
        $this->assertOwned($warehouse, $instituteId);

        if ($warehouse->stockLevels()->where('quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'warehouse' => 'This warehouse still holds stock and cannot be deleted.',
            ]);
        }

        $warehouse->delete();
    }

    public function createItem(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): InventoryItem
    {
        $data = $this->validateItem($instituteId, $branchId, $data);

        return InventoryItem::create(array_merge($data, [
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'created_by' => $actorId,
        ]));
    }

    public function updateItem(InventoryItem $item, int $instituteId, array $data, ?int $actorId = null): InventoryItem
    {
        $this->assertOwned($item, $instituteId);
        $data = $this->validateItem($instituteId, $item->branch_id, $data, exceptSku: $item->sku);

        $item->forceFill(array_merge($data, ['updated_by' => $actorId]))->save();

        return $item->fresh();
    }

    public function deleteItem(InventoryItem $item, int $instituteId): void
    {
        $this->assertOwned($item, $instituteId);

        if ($item->stockLevels()->where('quantity', '<>', 0)->exists()) {
            throw ValidationException::withMessages([
                'item' => 'This item still has stock and cannot be deleted.',
            ]);
        }

        $item->delete();
    }

    public function listItems(int $instituteId, ?int $branchId, array $filters = []): Builder
    {
        return InventoryItem::query()
            ->where('institute_id', $instituteId)
            ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
            ->when(filled($filters['category_id'] ?? null), fn ($query) => $query->where('category_id', $filters['category_id']))
            ->when(filled($filters['item_type'] ?? null), fn ($query) => $query->where('item_type', $filters['item_type']))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $filters['is_active']))
            ->when(filled($filters['search'] ?? null), fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('sku', 'like', '%'.$filters['search'].'%')
                ->orWhere('barcode', 'like', '%'.$filters['search'].'%')));
    }

    private function validateCategory(int $instituteId, ?int $branchId, array $data, ?string $exceptName = null): array
    {
        $validated = validator($data, [
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'inventory_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'cogs_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'sales_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'expense_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        $nameExists = InventoryCategory::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('name', $validated['name'] ?? '')
            ->when($exceptName !== null, fn ($query) => $query->where('name', '<>', $exceptName))
            ->exists();

        if ($nameExists) {
            throw ValidationException::withMessages(['name' => 'A category with this name already exists.']);
        }

        return $validated;
    }

    private function validateWarehouse(int $instituteId, ?int $branchId, array $data, ?string $exceptCode = null): array
    {
        $validated = validator($data, [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        $codeExists = InventoryWarehouse::query()
            ->where('institute_id', $instituteId)
            ->where('branch_id', $branchId)
            ->where('code', $validated['code'] ?? '')
            ->when($exceptCode !== null, fn ($query) => $query->where('code', '<>', $exceptCode))
            ->exists();

        if ($codeExists) {
            throw ValidationException::withMessages(['code' => 'A warehouse with this code already exists.']);
        }

        return $validated;
    }

    private function validateItem(int $instituteId, ?int $branchId, array $data, ?string $exceptSku = null): array
    {
        $validated = validator($data, [
            'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'item_type' => ['required', 'string', 'in:'.implode(',', self::ITEM_TYPES)],
            'sku' => ['nullable', 'string', 'max:60'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:30'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'tax_group_id' => ['nullable', 'integer', 'exists:tax_groups,id'],
            'inventory_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'cogs_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'sales_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'expense_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'is_active' => ['nullable', 'boolean'],
        ])->validate();

        if (! empty($validated['category_id'])) {
            $owned = InventoryCategory::query()
                ->where('institute_id', $instituteId)
                ->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->whereKey($validated['category_id'])
                ->exists();

            if (! $owned) {
                throw ValidationException::withMessages(['category_id' => 'The selected category does not belong to this institute.']);
            }
        }

        if (! empty($validated['sku'])) {
            $skuExists = InventoryItem::query()
                ->where('institute_id', $instituteId)
                ->where('branch_id', $branchId)
                ->where('sku', $validated['sku'])
                ->when($exceptSku !== null, fn ($query) => $query->where('sku', '<>', $exceptSku))
                ->exists();

            if ($skuExists) {
                throw ValidationException::withMessages(['sku' => 'An item with this SKU already exists.']);
            }
        }

        if (! empty($validated['barcode'])) {
            $barcodeExists = InventoryItem::query()
                ->where('institute_id', $instituteId)
                ->where('barcode', $validated['barcode'])
                ->exists();

            if ($barcodeExists) {
                throw ValidationException::withMessages(['barcode' => 'An item with this barcode already exists.']);
            }
        }

        return $validated;
    }

    private function assertOwned(InventoryCategory|InventoryWarehouse|InventoryItem $model, int $instituteId): void
    {
        if ((int) $model->institute_id !== (int) $instituteId) {
            throw ValidationException::withMessages([
                'record' => 'This record does not belong to the given institute.',
            ]);
        }
    }
}
