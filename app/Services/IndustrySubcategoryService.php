<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class IndustrySubcategoryService
{
    /**
     * Get modules for a sub-category (grouped by category).
     *
     * 'hidden' holds modules the platform deliberately keeps out of the tenant
     * UI for this sub-category — Layer 3 never auto-enables them and the tenant
     * module screen neither lists nor toggles them.
     *
     * @return array{mandatory: array<int, string>, default: array<int, string>, optional: array<int, string>, hidden: array<int, string>}
     */
    public function getModules(string $industry, string $subcategory): array
    {
        $sub = DB::table('industry_subcategories')
            ->where('industry_key', $industry)
            ->where('subcategory_key', $subcategory)
            ->where('is_active', true)
            ->first();

        if (! $sub) {
            return ['mandatory' => [], 'default' => [], 'optional' => [], 'hidden' => []];
        }

        $modules = DB::table('subcategory_default_modules')
            ->where('subcategory_id', $sub->id)
            ->get();

        return [
            'mandatory' => $modules->where('category', 'mandatory')->pluck('module_key')->toArray(),
            'default' => $modules->where('category', 'default')->pluck('module_key')->toArray(),
            'optional' => $modules->where('category', 'optional')->pluck('module_key')->toArray(),
            'hidden' => $modules->where('category', 'hidden')->pluck('module_key')->toArray(),
        ];
    }

    /**
     * List all active sub-categories for an industry, ordered by sort_order.
     *
     * @return array<int, object>
     */
    public function listForIndustry(string $industry): array
    {
        return DB::table('industry_subcategories')
            ->where('industry_key', $industry)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->toArray();
    }

    /**
     * Find a sub-category by industry + subcategory keys.
     */
    public function findByKey(string $industry, string $subcategory): ?object
    {
        return DB::table('industry_subcategories')
            ->where('industry_key', $industry)
            ->where('subcategory_key', $subcategory)
            ->first();
    }
}
