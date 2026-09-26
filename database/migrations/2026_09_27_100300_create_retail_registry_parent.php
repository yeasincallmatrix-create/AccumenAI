<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Foundation Fix 4 — `retail` exists in the industries taxonomy and in
     * package_industries, but had no module_registry parent, making the
     * package-industry mapping an orphan. Creates the registry parent
     * (type=industry, parent_key NULL). No children are added - retail
     * children come in a later phase.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        $existing = DB::table('module_registry')->where('key', 'retail')->first();
        if ($existing) {
            echo "retail parent already exists (id={$existing->id}). Skipping.\n";

            return;
        }

        DB::table('module_registry')->insert([
            'key' => 'retail',
            'name' => 'Retail',
            'type' => 'industry',
            'parent_key' => null,
            'description' => 'Retail industry (grocery, electronics, clothing, etc.)',
            'dependencies' => null,
            'sort_order' => 17,
            'icon' => 'bi-shop',
            'coming_soon' => false,
            'index_route' => null,
            'status' => 'active',
            'is_core' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "Created retail registry parent.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')
            ->where('key', 'retail')
            ->whereNull('parent_key')
            ->delete();
    }
};
