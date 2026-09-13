<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insert([
            ['slug' => 'purchase_order.view', 'name' => 'View Purchase Orders', 'module' => 'purchase', 'created_at' => $now],
            ['slug' => 'purchase_order.create', 'name' => 'Create Purchase Orders', 'module' => 'purchase', 'created_at' => $now],
            ['slug' => 'purchase_order.update', 'name' => 'Update Purchase Orders', 'module' => 'purchase', 'created_at' => $now],
            ['slug' => 'purchase_order.delete', 'name' => 'Delete Purchase Orders', 'module' => 'purchase', 'created_at' => $now],
            ['slug' => 'purchase_order.approve', 'name' => 'Approve Purchase Orders', 'module' => 'purchase', 'created_at' => $now],
            ['slug' => 'goods_receipt.view', 'name' => 'View Goods Receipts', 'module' => 'purchase', 'created_at' => $now],
            ['slug' => 'goods_receipt.create', 'name' => 'Create Goods Receipts', 'module' => 'purchase', 'created_at' => $now],
            ['slug' => 'goods_receipt.confirm', 'name' => 'Confirm Goods Receipts', 'module' => 'purchase', 'created_at' => $now],
            ['slug' => 'goods_receipt.cancel', 'name' => 'Cancel Goods Receipts', 'module' => 'purchase', 'created_at' => $now],
        ]);

        $rolePermissions = [];

        $ownerRoleId = DB::table('roles')->where('slug', 'institute-owner')->value('id');
        if ($ownerRoleId) {
            foreach (['purchase_order.view', 'purchase_order.create', 'purchase_order.update', 'purchase_order.delete', 'purchase_order.approve', 'goods_receipt.view', 'goods_receipt.create', 'goods_receipt.confirm', 'goods_receipt.cancel'] as $perm) {
                $permId = DB::table('permissions')->where('slug', $perm)->value('id');
                if ($permId) {
                    $rolePermissions[] = ['role_id' => $ownerRoleId, 'permission_id' => $permId];
                }
            }
        }

        $adminRoleId = DB::table('roles')->where('slug', 'institute-admin')->value('id');
        if ($adminRoleId) {
            foreach (['purchase_order.view', 'purchase_order.create', 'purchase_order.update', 'purchase_order.delete', 'purchase_order.approve', 'goods_receipt.view', 'goods_receipt.create', 'goods_receipt.confirm', 'goods_receipt.cancel'] as $perm) {
                $permId = DB::table('permissions')->where('slug', $perm)->value('id');
                if ($permId) {
                    $rolePermissions[] = ['role_id' => $adminRoleId, 'permission_id' => $permId];
                }
            }
        }

        $bmRoleId = DB::table('roles')->where('slug', 'branch-manager')->value('id');
        if ($bmRoleId) {
            foreach (['purchase_order.view', 'purchase_order.create', 'goods_receipt.view', 'goods_receipt.create'] as $perm) {
                $permId = DB::table('permissions')->where('slug', $perm)->value('id');
                if ($permId) {
                    $rolePermissions[] = ['role_id' => $bmRoleId, 'permission_id' => $permId];
                }
            }
        }

        $accRoleId = DB::table('roles')->where('slug', 'accountant')->value('id');
        if ($accRoleId) {
            foreach (['purchase_order.view', 'purchase_order.approve', 'goods_receipt.view', 'goods_receipt.confirm'] as $perm) {
                $permId = DB::table('permissions')->where('slug', $perm)->value('id');
                if ($permId) {
                    $rolePermissions[] = ['role_id' => $accRoleId, 'permission_id' => $permId];
                }
            }
        }

        if ($rolePermissions) {
            DB::table('role_permissions')->insert($rolePermissions);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('slug', [
            'purchase_order.view', 'purchase_order.create', 'purchase_order.update',
            'purchase_order.delete', 'purchase_order.approve',
            'goods_receipt.view', 'goods_receipt.create', 'goods_receipt.confirm', 'goods_receipt.cancel',
        ])->delete();
    }
};
