<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permissions')) {
            return;
        }

        $permissions = [
            // API gates that had no seeder (deleted baseline migrations).
            ['slug' => 'notifications.view', 'module' => 'notifications', 'name' => 'View Notifications'],
            ['slug' => 'hr.view',            'module' => 'hr',            'name' => 'View HR'],

            // Accounting (AccountingPermissionSeeder — was never wired).
            ['slug' => 'accounts.view',              'module' => 'accounting', 'name' => 'View Chart of Accounts'],
            ['slug' => 'accounts.create',            'module' => 'accounting', 'name' => 'Create Accounts'],
            ['slug' => 'accounts.edit',              'module' => 'accounting', 'name' => 'Edit Accounts'],
            ['slug' => 'accounts.delete',            'module' => 'accounting', 'name' => 'Delete Accounts'],
            ['slug' => 'journals.post',              'module' => 'accounting', 'name' => 'Post Journals'],
            ['slug' => 'journals.reverse',           'module' => 'accounting', 'name' => 'Reverse Journals'],
            ['slug' => 'journals.void',              'module' => 'accounting', 'name' => 'Void Journals'],
            ['slug' => 'reports.financial.view',     'module' => 'accounting', 'name' => 'View Financial Reports'],
            ['slug' => 'settings.accounting.manage', 'module' => 'accounting', 'name' => 'Manage Accounting Settings'],
        ];

        $columns = DB::getSchemaBuilder()->getColumnListing('permissions');
        $timestamps = [];
        if (in_array('created_at', $columns, true)) {
            $timestamps['created_at'] = now();
        }
        if (in_array('updated_at', $columns, true)) {
            $timestamps['updated_at'] = now();
        }

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $perm['slug']],
                $perm + $timestamps
            );
        }

        // Grant new slugs to the global institute-owner role (same as RolePermissionSeeder).
        $ownerRole = DB::table('roles')
            ->where('slug', 'institute-owner')
            ->whereNull('institute_id')
            ->first();

        if ($ownerRole) {
            $permIds = DB::table('permissions')
                ->whereIn('slug', array_column($permissions, 'slug'))
                ->pluck('id');

            $existing = DB::table('role_permissions')
                ->where('role_id', $ownerRole->id)
                ->whereIn('permission_id', $permIds)
                ->pluck('permission_id');

            foreach ($permIds->diff($existing) as $permId) {
                DB::table('role_permissions')->insert([
                    'role_id'       => $ownerRole->id,
                    'permission_id' => $permId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data backfill only — intentionally a no-op.
    }
};
