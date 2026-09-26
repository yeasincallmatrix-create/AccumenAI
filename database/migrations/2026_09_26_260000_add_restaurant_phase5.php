<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 5 — Restaurant Delivery & Online Ordering: 6 more children
     * under the existing `restaurant` industry (delivery_zone,
     * delivery_rider, online_order, qr_order, kiosk, tracking). Children
     * inherit type + is_core from the parent row and the whole block is
     * updateOrInsert, so a re-run is a no-op. Phases 1-4 rows are never
     * touched.
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
            ['key' => 'restaurant.delivery_zone', 'name' => 'Delivery Zones', 'icon' => 'bi-geo', 'sort_order' => 50],
            ['key' => 'restaurant.delivery_rider', 'name' => 'Delivery Riders', 'icon' => 'bi-person-biking', 'sort_order' => 51],
            ['key' => 'restaurant.online_order', 'name' => 'Online Orders', 'icon' => 'bi-globe', 'sort_order' => 52],
            ['key' => 'restaurant.qr_order', 'name' => 'QR Code Ordering', 'icon' => 'bi-qr-code', 'sort_order' => 53],
            ['key' => 'restaurant.kiosk', 'name' => 'Self-Service Kiosk', 'icon' => 'bi-tablet', 'sort_order' => 54],
            ['key' => 'restaurant.tracking', 'name' => 'Live Tracking', 'icon' => 'bi-geo-alt', 'sort_order' => 55],
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

        echo "Inserted {$inserted} restaurant phase 5 modules.\n";
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_registry')) {
            return;
        }

        DB::table('module_registry')->whereIn('key', [
            'restaurant.delivery_zone', 'restaurant.delivery_rider', 'restaurant.online_order',
            'restaurant.qr_order', 'restaurant.kiosk', 'restaurant.tracking',
        ])->delete();
    }
};
