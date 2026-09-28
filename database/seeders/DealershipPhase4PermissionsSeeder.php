<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DealershipPhase4PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            ['slug' => 'sr_reports.view',           'name' => 'SR Reports View'],
            ['slug' => 'sr_reports.export',         'name' => 'SR Reports Export'],
            ['slug' => 'sales_reports.view',        'name' => 'Sales Reports View'],
            ['slug' => 'sales_reports.export',      'name' => 'Sales Reports Export'],
            ['slug' => 'collection_reports.view',   'name' => 'Collection Reports View'],
            ['slug' => 'collection_reports.export', 'name' => 'Collection Reports Export'],
            ['slug' => 'target_reports.view',       'name' => 'Target Reports View'],
            ['slug' => 'target_reports.export',     'name' => 'Target Reports Export'],
            ['slug' => 'dashboard.view',            'name' => 'Dashboard View'],
        ];

        foreach ($perms as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $perm['slug']],
                ['module' => 'dealership', 'name' => $perm['name'], 'created_at' => now()]
            );
        }
    }
}
