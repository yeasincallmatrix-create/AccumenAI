<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant module permissions (45 slugs) once restaurant is a MAIN
 * industry (Phase 2), extended with the Phase 2 order-type and Phase 3
 * kitchen-operation modules.
 *
 * Mirrors SalesPurchasePermissionSeeder: permissions only - role assignment
 * is RolePermissionSeeder / module-activator territory.
 *
 * `name` carries the same value as `slug` so the permission is addressable by
 * its key everywhere it is listed (slug is the unique lookup key).
 *
 * Idempotent via firstOrCreate by slug - safe to re-run.
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=RestaurantPermissionSeeder
 */
class RestaurantPermissionSeeder extends Seeder
{
    /**
     * Canonical 45 permissions: 18 sub-modules x (view + manage) = 36,
     * plus 10 module-level capabilities, minus 1 overlap
     * (restaurant.kitchen.view is both the kitchen view permission and the
     * module-level kitchen capability) -> 36 + 10 - 1 = 45.
     *
     * @return array<int, array{slug: string, module: string, name: string}>
     */
    public static function permissions(): array
    {
        return [
            // Sub-modules (12)
            ['slug' => 'restaurant.menu.view', 'module' => 'restaurant', 'name' => 'restaurant.menu.view'],
            ['slug' => 'restaurant.menu.manage', 'module' => 'restaurant', 'name' => 'restaurant.menu.manage'],
            ['slug' => 'restaurant.menu_category.view', 'module' => 'restaurant', 'name' => 'restaurant.menu_category.view'],
            ['slug' => 'restaurant.menu_category.manage', 'module' => 'restaurant', 'name' => 'restaurant.menu_category.manage'],
            ['slug' => 'restaurant.menu_item.view', 'module' => 'restaurant', 'name' => 'restaurant.menu_item.view'],
            ['slug' => 'restaurant.menu_item.manage', 'module' => 'restaurant', 'name' => 'restaurant.menu_item.manage'],
            ['slug' => 'restaurant.table.view', 'module' => 'restaurant', 'name' => 'restaurant.table.view'],
            ['slug' => 'restaurant.table.manage', 'module' => 'restaurant', 'name' => 'restaurant.table.manage'],
            ['slug' => 'restaurant.table_layout.view', 'module' => 'restaurant', 'name' => 'restaurant.table_layout.view'],
            ['slug' => 'restaurant.table_layout.manage', 'module' => 'restaurant', 'name' => 'restaurant.table_layout.manage'],
            ['slug' => 'restaurant.reservation.view', 'module' => 'restaurant', 'name' => 'restaurant.reservation.view'],
            ['slug' => 'restaurant.reservation.manage', 'module' => 'restaurant', 'name' => 'restaurant.reservation.manage'],

            // Order types (Phase 2): 6 modules x (view + manage) = 12
            ['slug' => 'restaurant.dine_in.view', 'module' => 'restaurant', 'name' => 'restaurant.dine_in.view'],
            ['slug' => 'restaurant.dine_in.manage', 'module' => 'restaurant', 'name' => 'restaurant.dine_in.manage'],
            ['slug' => 'restaurant.takeaway.view', 'module' => 'restaurant', 'name' => 'restaurant.takeaway.view'],
            ['slug' => 'restaurant.takeaway.manage', 'module' => 'restaurant', 'name' => 'restaurant.takeaway.manage'],
            ['slug' => 'restaurant.delivery.view', 'module' => 'restaurant', 'name' => 'restaurant.delivery.view'],
            ['slug' => 'restaurant.delivery.manage', 'module' => 'restaurant', 'name' => 'restaurant.delivery.manage'],
            ['slug' => 'restaurant.order.view', 'module' => 'restaurant', 'name' => 'restaurant.order.view'],
            ['slug' => 'restaurant.order.manage', 'module' => 'restaurant', 'name' => 'restaurant.order.manage'],
            ['slug' => 'restaurant.order_tracking.view', 'module' => 'restaurant', 'name' => 'restaurant.order_tracking.view'],
            ['slug' => 'restaurant.order_tracking.manage', 'module' => 'restaurant', 'name' => 'restaurant.order_tracking.manage'],
            ['slug' => 'restaurant.pre_order.view', 'module' => 'restaurant', 'name' => 'restaurant.pre_order.view'],
            ['slug' => 'restaurant.pre_order.manage', 'module' => 'restaurant', 'name' => 'restaurant.pre_order.manage'],

            // Kitchen operations (Phase 3): 6 modules x (view + manage) = 12,
            // minus restaurant.kitchen.view which is already declared as a
            // module-level capability below -> 11 new slugs.
            ['slug' => 'restaurant.kitchen.manage', 'module' => 'restaurant', 'name' => 'restaurant.kitchen.manage'],
            ['slug' => 'restaurant.kds.view', 'module' => 'restaurant', 'name' => 'restaurant.kds.view'],
            ['slug' => 'restaurant.kds.manage', 'module' => 'restaurant', 'name' => 'restaurant.kds.manage'],
            ['slug' => 'restaurant.kot.view', 'module' => 'restaurant', 'name' => 'restaurant.kot.view'],
            ['slug' => 'restaurant.kot.manage', 'module' => 'restaurant', 'name' => 'restaurant.kot.manage'],
            ['slug' => 'restaurant.chef.view', 'module' => 'restaurant', 'name' => 'restaurant.chef.view'],
            ['slug' => 'restaurant.chef.manage', 'module' => 'restaurant', 'name' => 'restaurant.chef.manage'],
            ['slug' => 'restaurant.station.view', 'module' => 'restaurant', 'name' => 'restaurant.station.view'],
            ['slug' => 'restaurant.station.manage', 'module' => 'restaurant', 'name' => 'restaurant.station.manage'],
            ['slug' => 'restaurant.recipe.view', 'module' => 'restaurant', 'name' => 'restaurant.recipe.view'],
            ['slug' => 'restaurant.recipe.manage', 'module' => 'restaurant', 'name' => 'restaurant.recipe.manage'],

            // Module-level capabilities (10)
            ['slug' => 'restaurant.dashboard.view', 'module' => 'restaurant', 'name' => 'restaurant.dashboard.view'],
            ['slug' => 'restaurant.orders.view', 'module' => 'restaurant', 'name' => 'restaurant.orders.view'],
            ['slug' => 'restaurant.orders.manage', 'module' => 'restaurant', 'name' => 'restaurant.orders.manage'],
            ['slug' => 'restaurant.reports.view', 'module' => 'restaurant', 'name' => 'restaurant.reports.view'],
            ['slug' => 'restaurant.settings.manage', 'module' => 'restaurant', 'name' => 'restaurant.settings.manage'],
            ['slug' => 'restaurant.pos.view', 'module' => 'restaurant', 'name' => 'restaurant.pos.view'],
            ['slug' => 'restaurant.kitchen.view', 'module' => 'restaurant', 'name' => 'restaurant.kitchen.view'],
            ['slug' => 'restaurant.billing.view', 'module' => 'restaurant', 'name' => 'restaurant.billing.view'],
            ['slug' => 'restaurant.customers.view', 'module' => 'restaurant', 'name' => 'restaurant.customers.view'],
            ['slug' => 'restaurant.analytics.view', 'module' => 'restaurant', 'name' => 'restaurant.analytics.view'],
        ];
    }

    public function run(): void
    {
        if (! Schema::hasTable('permissions')) {
            $this->command?->error('permissions table missing - run migrations first.');

            return;
        }

        $inserted = 0;

        foreach (self::permissions() as $permission) {
            $model = Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                [
                    'module' => $permission['module'],
                    'name' => $permission['name'],
                ]
            );

            if ($model->wasRecentlyCreated) {
                $inserted++;
            }
        }

        $total = count(self::permissions());
        $this->command?->info("Restaurant permissions seeded: {$inserted} new of {$total}.");
    }
}
