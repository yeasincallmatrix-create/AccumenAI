<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Seed the sales (23) and purchase (22) module permissions.
 *
 * Mirrors TaxPermissionSeeder / AccountingPermissionSeeder: the permissions
 * table ships from the SQL dump with zero sales.* / purchase.* rows, so
 * middleware (permission:sales.view, permission:purchase.manage, …) and
 * sidebar gating (AppServiceProvider) fail for every tenant.
 *
 * Idempotent via firstOrCreate by slug — safe to re-run.
 * Does NOT attach to roles (that is RolePermissionSeeder territory).
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=SalesPurchasePermissionSeeder
 */
class SalesPurchasePermissionSeeder extends Seeder
{
    /**
     * Canonical 45 permissions: 23 sales + 22 purchase.
     *
     * Legacy seed_data.sql rows (sales.view/create/update/delete/manage and
     * purchase.view/manage/create) are included so firstOrCreate is a no-op
     * when they already exist. purchase_order.* / goods_receipt.* are NOT
     * touched here (legacy API aliases, module=purchase).
     */
    public static function permissions(): array
    {
        return [
            // ── SALES (23) ────────────────────────────────────────────────
            ['slug' => 'sales.view',                'module' => 'sales', 'name' => 'View Sales'],
            ['slug' => 'sales.manage',              'module' => 'sales', 'name' => 'Manage Sales'],
            ['slug' => 'sales.create',              'module' => 'sales', 'name' => 'Create Sales'],
            ['slug' => 'sales.update',              'module' => 'sales', 'name' => 'Update Sales'],
            ['slug' => 'sales.delete',              'module' => 'sales', 'name' => 'Delete Sales'],
            ['slug' => 'sales.invoices.view',       'module' => 'sales', 'name' => 'View Sales Invoices'],
            ['slug' => 'sales.invoices.create',     'module' => 'sales', 'name' => 'Create Sales Invoices'],
            ['slug' => 'sales.invoices.update',     'module' => 'sales', 'name' => 'Update Sales Invoices'],
            ['slug' => 'sales.invoices.delete',     'module' => 'sales', 'name' => 'Delete Sales Invoices'],
            ['slug' => 'sales.payments.view',       'module' => 'sales', 'name' => 'View Sales Payments'],
            ['slug' => 'sales.payments.create',     'module' => 'sales', 'name' => 'Receive Payment'],
            ['slug' => 'sales.payments.delete',     'module' => 'sales', 'name' => 'Delete Sales Payments'],
            ['slug' => 'sales.estimates.view',      'module' => 'sales', 'name' => 'View Estimates'],
            ['slug' => 'sales.estimates.create',    'module' => 'sales', 'name' => 'Create Estimates'],
            ['slug' => 'sales.estimates.update',    'module' => 'sales', 'name' => 'Update Estimates'],
            ['slug' => 'sales.estimates.delete',    'module' => 'sales', 'name' => 'Delete Estimates'],
            ['slug' => 'sales.orders.view',         'module' => 'sales', 'name' => 'View Sales Orders'],
            ['slug' => 'sales.orders.create',       'module' => 'sales', 'name' => 'Create Sales Orders'],
            ['slug' => 'sales.orders.update',       'module' => 'sales', 'name' => 'Update Sales Orders'],
            ['slug' => 'sales.orders.delete',       'module' => 'sales', 'name' => 'Delete Sales Orders'],
            ['slug' => 'sales.credit_memos.view',   'module' => 'sales', 'name' => 'View Credit Memos'],
            ['slug' => 'sales.credit_memos.create', 'module' => 'sales', 'name' => 'Create Credit Memos'],
            ['slug' => 'sales.receipts.view',       'module' => 'sales', 'name' => 'View Sales Receipts'],
            ['slug' => 'sales.receipts.create',     'module' => 'sales', 'name' => 'Create Sales Receipts'],
            ['slug' => 'sales.customers.manage',    'module' => 'sales', 'name' => 'Manage Customers'],

            // ── PURCHASE (22) ────────────────────────────────────────────
            ['slug' => 'purchase.view',             'module' => 'purchase', 'name' => 'View Purchase'],
            ['slug' => 'purchase.manage',           'module' => 'purchase', 'name' => 'Manage Purchase'],
            ['slug' => 'purchase.create',           'module' => 'purchase', 'name' => 'Create Purchase'],
            ['slug' => 'purchase.update',           'module' => 'purchase', 'name' => 'Update Purchase'],
            ['slug' => 'purchase.delete',           'module' => 'purchase', 'name' => 'Delete Purchase'],
            ['slug' => 'purchase.bills.view',       'module' => 'purchase', 'name' => 'View Bills'],
            ['slug' => 'purchase.bills.create',     'module' => 'purchase', 'name' => 'Create Bills'],
            ['slug' => 'purchase.bills.update',     'module' => 'purchase', 'name' => 'Update Bills'],
            ['slug' => 'purchase.bills.delete',     'module' => 'purchase', 'name' => 'Delete Bills'],
            ['slug' => 'purchase.payments.view',    'module' => 'purchase', 'name' => 'View Bill Payments'],
            ['slug' => 'purchase.payments.create',  'module' => 'purchase', 'name' => 'Pay Bills'],
            ['slug' => 'purchase.payments.delete',  'module' => 'purchase', 'name' => 'Delete Bill Payments'],
            ['slug' => 'purchase.orders.view',      'module' => 'purchase', 'name' => 'View Purchase Orders'],
            ['slug' => 'purchase.orders.create',    'module' => 'purchase', 'name' => 'Create Purchase Orders'],
            ['slug' => 'purchase.orders.update',    'module' => 'purchase', 'name' => 'Update Purchase Orders'],
            ['slug' => 'purchase.orders.delete',    'module' => 'purchase', 'name' => 'Delete Purchase Orders'],
            ['slug' => 'purchase.orders.approve',   'module' => 'purchase', 'name' => 'Approve Purchase Orders'],
            ['slug' => 'purchase.expenses.view',    'module' => 'purchase', 'name' => 'View Expenses'],
            ['slug' => 'purchase.expenses.create',  'module' => 'purchase', 'name' => 'Create Expenses'],
            ['slug' => 'purchase.expenses.update',  'module' => 'purchase', 'name' => 'Update Expenses'],
            ['slug' => 'purchase.expenses.delete',  'module' => 'purchase', 'name' => 'Delete Expenses'],
            ['slug' => 'purchase.vendors.manage',   'module' => 'purchase', 'name' => 'Manage Vendors'],
        ];
    }

    public function run(): void
    {
        foreach (self::permissions() as $perm) {
            Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                ['module' => $perm['module'], 'name' => $perm['name']],
            );
        }

        $this->command?->info('Sales/Purchase permissions seeded ('.count(self::permissions()).' slugs).');
    }
}
