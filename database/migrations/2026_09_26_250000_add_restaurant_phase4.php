<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 4 — Restaurant Customer & Loyalty: 6 more children under the
     * existing `restaurant` industry (customer, loyalty, feedback,
     * membership, birthday_offer, preference). Children inherit type +
     * is_core from the parent row and the whole block is updateOrInsert,
     * so a re-run is a no-op. Phases 1-3 rows are never touched.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $parent = DB::table('module_registry')->where('key', 'restaurant')->first();
        if (! $parent) {
            throw new \RuntimeException('Restaurant parent missing');
        }

        $parentType = $parent->type ?? 'industry';
        $parentIsCore = $parent->is_core ?? 0;

        $children = [
            ['key' => 'restaurant.customer', 'name' => 'Customer Management', 'icon' => 'bi-person-vcard', 'sort_order' => 40],
            ['key' => 'restaurant.loyalty', 'name' => 'Loyalty Program', 'icon' => 'bi-award', 'sort_order' => 41],
            ['key' => 'restaurant.feedback', 'name' => 'Feedback & Reviews', 'icon' => 'bi-star', 'sort_order' => 42],
            ['key' => 'restaurant.membership', 'name' => 'Membership Tiers', 'icon' => 'bi-gem', 'sort_order' => 43],
            ['key' => 'restaurant.birthday_offer', 'name' => 'Birthday Offers', 'icon' => 'bi-gift', 'sort_order' => 44],
            ['key' => 'restaurant.preference', 'name' => 'Customer Preferences', 'icon' => 'bi-sliders', 'sort_order' => 45],
        ];

        $inserted = 0;
        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'restaurant',
                    'type' => $parentType,
                    'is_core' => $parentIsCore,
                    'description' => null,
                    'dependencies' => null,
                    'sort_order' => $child['sort_order'],
                    'icon' => $child['icon'],
                    'coming_soon' => false,
                    'index_route' => null,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $inserted++;
        }

        echo "Inserted {$inserted} restaurant phase 4 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'restaurant.customer', 'restaurant.loyalty', 'restaurant.feedback',
            'restaurant.membership', 'restaurant.birthday_offer', 'restaurant.preference',
        ])->delete();
    }
};
