<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['slug' => 'budget.view', 'module' => 'budgeting', 'name' => 'Budget View'],
            ['slug' => 'budget.create', 'module' => 'budgeting', 'name' => 'Budget Create'],
            ['slug' => 'budget.edit', 'module' => 'budgeting', 'name' => 'Budget Edit'],
            ['slug' => 'budget.submit', 'module' => 'budgeting', 'name' => 'Budget Submit'],
            ['slug' => 'budget.approve', 'module' => 'budgeting', 'name' => 'Budget Approve'],
            ['slug' => 'budget.lock', 'module' => 'budgeting', 'name' => 'Budget Lock'],
            ['slug' => 'budget.revise', 'module' => 'budgeting', 'name' => 'Budget Revise'],
            ['slug' => 'budget.report', 'module' => 'budgeting', 'name' => 'Budget Report'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->insert(array_merge($perm, [
                'created_at' => now(),
            ]));
        }

        $ownerRole = DB::table('roles')->where('slug', 'institute-owner')->first();
        $adminRole = DB::table('roles')->where('slug', 'institute-admin')->first();
        $accountantRole = DB::table('roles')->where('slug', 'accountant')->first();
        $branchManagerRole = DB::table('roles')->where('slug', 'branch-manager')->first();

        $budgetPerms = DB::table('permissions')->whereIn('module', ['budgeting'])->pluck('id', 'slug');

        $grantMap = [];
        if ($ownerRole) {
            foreach ($budgetPerms as $slug => $id) {
                $grantMap[] = ['role_id' => $ownerRole->id, 'permission_id' => $id];
            }
        }
        if ($adminRole) {
            foreach ($budgetPerms as $slug => $id) {
                $grantMap[] = ['role_id' => $adminRole->id, 'permission_id' => $id];
            }
        }
        if ($accountantRole) {
            foreach ($budgetPerms as $slug => $id) {
                $grantMap[] = ['role_id' => $accountantRole->id, 'permission_id' => $id];
            }
        }
        if ($branchManagerRole) {
            $branchPerms = ['budget.view', 'budget.create', 'budget.submit', 'budget.report'];
            foreach ($branchPerms as $slug) {
                if (isset($budgetPerms[$slug])) {
                    $grantMap[] = ['role_id' => $branchManagerRole->id, 'permission_id' => $budgetPerms[$slug]];
                }
            }
        }

        foreach ($grantMap as $grant) {
            DB::table('role_permissions')->insert($grant);
        }
    }

    public function down(): void
    {
        $budgetPerms = DB::table('permissions')->where('module', 'budgeting')->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $budgetPerms)->delete();
        DB::table('permissions')->where('module', 'budgeting')->delete();
    }
};
