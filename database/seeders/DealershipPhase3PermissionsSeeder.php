<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DealershipPhase3PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            'sr_targets' => 'SR Targets',
            'sr_commission' => 'SR Commission',
            'brand_targets' => 'Brand Targets',
            'incentives' => 'Incentives',
            'attendance' => 'Attendance',
        ];

        foreach ($modules as $slug => $label) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => "{$slug}.view"],
                ['module' => 'dealership', 'name' => "{$label} View", 'created_at' => now()]
            );
            DB::table('permissions')->updateOrInsert(
                ['slug' => "{$slug}.manage"],
                ['module' => 'dealership', 'name' => "{$label} Manage", 'created_at' => now()]
            );
        }

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'sr_commission.approve'],
            ['module' => 'dealership', 'name' => 'SR Commission Approve', 'created_at' => now()]
        );
    }
}
