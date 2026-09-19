<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $ownerRole = DB::table('roles')->where('slug', 'institute-owner')->whereNull('institute_id')->first();

        if (! $ownerRole) {
            $this->command?->warn('institute-owner role not found, skipping role_permissions seed.');

            return;
        }

        $allPermissions = DB::table('permissions')->pluck('id')->toArray();

        if (empty($allPermissions)) {
            $this->command?->warn('No permissions found, skipping role_permissions seed.');

            return;
        }

        $existing = DB::table('role_permissions')
            ->where('role_id', $ownerRole->id)
            ->pluck('permission_id')
            ->toArray();

        $toInsert = array_diff($allPermissions, $existing);

        foreach ($toInsert as $permId) {
            DB::table('role_permissions')->insert([
                'role_id'       => $ownerRole->id,
                'permission_id' => $permId,
            ]);
        }

        $this->command?->info('Role permissions: ' . count($toInsert) . ' new assignments for institute-owner.');
    }
}
