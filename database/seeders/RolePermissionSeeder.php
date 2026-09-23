<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    /**
     * Sales/purchase permission grants by role.
     * teacher/receptionist intentionally excluded — tests expect 403.
     * branch-manager: view+create/update, NOT manage (write tests expect 403).
     * institute-admin: full sales/purchase (lifecycle approve uses admin).
     */
    private const GRANTS = [
        'institute-owner' => 'sales_purchase_all',
        'institute-admin' => 'sales_purchase_all',
        'branch-manager'  => [
            'sales.view', 'sales.create', 'sales.update',
            'purchase.view', 'purchase.create', 'purchase.update',
        ],
        'accountant'      => [
            'sales.view', 'purchase.view',
        ],
    ];

    public function run(): void
    {
        $salesPurchaseSlugs = \Database\Seeders\SalesPurchasePermissionSeeder::permissions();
        $salesPurchaseIds = DB::table('permissions')->whereIn('slug', array_column($salesPurchaseSlugs, 'slug'))->pluck('id')->toArray();

        foreach (self::GRANTS as $slug => $grant) {
            $role = DB::table('roles')->where('slug', $slug)->whereNull('institute_id')->first();

            if (! $role) {
                $this->command?->warn("{$slug} role not found, skipping role_permissions seed.");

                continue;
            }

            $desired = $grant === 'sales_purchase_all'
                ? $salesPurchaseIds
                : DB::table('permissions')->whereIn('slug', $grant)->pluck('id')->toArray();

            $existing = DB::table('role_permissions')
                ->where('role_id', $role->id)
                ->pluck('permission_id')
                ->toArray();

            $toInsert = array_diff($desired, $existing);

            foreach ($toInsert as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id'       => $role->id,
                    'permission_id' => $permId,
                ]);
            }

            $this->command?->info('Role permissions: ' . count($toInsert) . " new assignments for {$slug}.");
        }
    }
}
