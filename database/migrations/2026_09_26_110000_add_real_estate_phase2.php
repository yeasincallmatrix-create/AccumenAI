<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 2 — Real Estate Leasing & Rental: 8 more children under the
     * existing `real_estate` parent (Phase 1 added 7, so 15 total).
     *
     * Idempotent: updateOrInsert keyed on `key`, so a re-run is a no-op.
     * Phase 1 rows are never touched.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $parent = DB::table('module_registry')->where('key', 'real_estate')->first();

        $children = [
            ['key' => 'real_estate.leases', 'name' => 'Leases', 'icon' => 'bi-file-earmark-text', 'sort_order' => 10],
            ['key' => 'real_estate.tenants', 'name' => 'Tenants', 'icon' => 'bi-people', 'sort_order' => 11],
            ['key' => 'real_estate.rent_invoices', 'name' => 'Rent Invoices', 'icon' => 'bi-receipt', 'sort_order' => 12],
            ['key' => 'real_estate.rent_collection', 'name' => 'Rent Collection', 'icon' => 'bi-cash-stack', 'sort_order' => 13],
            ['key' => 'real_estate.security_deposits', 'name' => 'Security Deposits', 'icon' => 'bi-shield-lock', 'sort_order' => 14],
            ['key' => 'real_estate.lease_renewals', 'name' => 'Lease Renewals', 'icon' => 'bi-arrow-repeat', 'sort_order' => 15],
            ['key' => 'real_estate.utility_billing', 'name' => 'Utility Billing', 'icon' => 'bi-lightning', 'sort_order' => 16],
            ['key' => 'real_estate.cam_charges', 'name' => 'CAM Charges', 'icon' => 'bi-tools', 'sort_order' => 17],
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

        echo "Inserted {$inserted} real estate phase 2 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'real_estate.leases', 'real_estate.tenants', 'real_estate.rent_invoices',
            'real_estate.rent_collection', 'real_estate.security_deposits',
            'real_estate.lease_renewals', 'real_estate.utility_billing',
            'real_estate.cam_charges',
        ])->delete();
    }
};
