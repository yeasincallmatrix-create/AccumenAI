<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('module_registry')->where('key', 'pos')->first();
        $parentType = $parent->type ?? 'core';
        $parentIsCore = $parent->is_core ?? 0;

        $children = [
            ['key' => 'pos.return', 'name' => 'Returns', 'icon' => 'bi-arrow-return-left', 'sort_order' => 50],
            ['key' => 'pos.refund', 'name' => 'Refunds', 'icon' => 'bi-cash-coin', 'sort_order' => 51],
            ['key' => 'pos.exchange', 'name' => 'Exchanges', 'icon' => 'bi-arrow-left-right', 'sort_order' => 52],
            ['key' => 'pos.daily_report', 'name' => 'Daily Sales Report', 'icon' => 'bi-calendar-check', 'sort_order' => 60],
            ['key' => 'pos.item_report', 'name' => 'Item Sales Report', 'icon' => 'bi-list-ul', 'sort_order' => 61],
            ['key' => 'pos.cashier_report', 'name' => 'Cashier Report', 'icon' => 'bi-person-badge', 'sort_order' => 62],
        ];

        $inserted = 0;
        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'pos',
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

        echo "Inserted {$inserted} POS phase 4 modules.\n";
    }

    public function down(): void
    {
        DB::table('module_registry')->whereIn('key', [
            'pos.return', 'pos.refund', 'pos.exchange',
            'pos.daily_report', 'pos.item_report', 'pos.cashier_report',
        ])->delete();
    }
};
