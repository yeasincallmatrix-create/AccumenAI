<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['module' => 'online_payments', 'name' => 'View Online Payments', 'slug' => 'online_payments.view', 'created_at' => now()],
            ['module' => 'online_payments', 'name' => 'Manage Online Payments', 'slug' => 'online_payments.manage', 'created_at' => now()],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $perm['slug']],
                $perm,
            );
        }

        $ownerRoleId = DB::table('roles')->where('slug', 'institute-owner')->value('id');
        $adminRoleId = DB::table('roles')->where('slug', 'institute-admin')->value('id');
        $accountantRoleId = DB::table('roles')->where('slug', 'accountant')->value('id');

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['online_payments.view', 'online_payments.manage'])
            ->pluck('id')
            ->toArray();

        foreach ([$ownerRoleId, $adminRoleId, $accountantRoleId] as $roleId) {
            if ($roleId === null) continue;
            foreach ($permissionIds as $permId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permId],
                    [],
                );
            }
        }

        DB::table('payment_gateways')->updateOrInsert(
            ['slug' => 'mock'],
            [
                'name' => 'Mock Gateway (Testing)',
                'description' => 'Simulated gateway for testing online payment flows.',
                'is_active' => true,
                'config_schema' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('payment_gateways')->where('slug', 'mock')->delete();
        DB::table('role_permissions')->whereIn('permission_id', function ($query) {
            $query->select('id')->from('permissions')->whereIn('slug', [
                'online_payments.view', 'online_payments.manage',
            ]);
        })->delete();
        DB::table('permissions')->whereIn('slug', [
            'online_payments.view', 'online_payments.manage',
        ])->delete();
    }
};
