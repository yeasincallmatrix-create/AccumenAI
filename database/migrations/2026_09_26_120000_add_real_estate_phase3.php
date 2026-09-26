<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 3 — Real Estate Sales & CRM: 7 more children under the existing
     * `real_estate` parent (Phase 1: 7, Phase 2: 8, Phase 3: 7 = 22 total).
     *
     * Idempotent: updateOrInsert keyed on `key`, so a re-run is a no-op.
     * Phase 1 and Phase 2 rows are never touched.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $parent = DB::table('module_registry')->where('key', 'real_estate')->first();

        $children = [
            ['key' => 'real_estate.leads', 'name' => 'Leads', 'icon' => 'bi-funnel', 'sort_order' => 20],
            ['key' => 'real_estate.site_visits', 'name' => 'Site Visits', 'icon' => 'bi-geo-alt', 'sort_order' => 21],
            ['key' => 'real_estate.bookings', 'name' => 'Bookings', 'icon' => 'bi-calendar-check', 'sort_order' => 22],
            ['key' => 'real_estate.sales_agreements', 'name' => 'Sales Agreements', 'icon' => 'bi-file-earmark-check', 'sort_order' => 23],
            ['key' => 'real_estate.installments', 'name' => 'Installments', 'icon' => 'bi-calendar-range', 'sort_order' => 24],
            ['key' => 'real_estate.handover', 'name' => 'Handover', 'icon' => 'bi-box-arrow-right', 'sort_order' => 25],
            ['key' => 'real_estate.after_sales', 'name' => 'After Sales', 'icon' => 'bi-headset', 'sort_order' => 26],
        ];

        $inserted = 0;
        foreach ($children as $child) {
            DB::table('module_registry')->updateOrInsert(
                ['key' => $child['key']],
                [
                    'name' => $child['name'],
                    'parent_key' => 'real_estate',
                    'type' => $parent->type ?? 'industry',
                    'is_core' => (int) ($parent->is_core ?? 0),
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

        echo "Inserted {$inserted} real estate phase 3 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'real_estate.leads', 'real_estate.site_visits', 'real_estate.bookings',
            'real_estate.sales_agreements', 'real_estate.installments',
            'real_estate.handover', 'real_estate.after_sales',
        ])->delete();
    }
};
