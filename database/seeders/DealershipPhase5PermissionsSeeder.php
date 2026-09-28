<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DealershipPhase5PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $perms = [
            ['slug' => 'api_tokens.view',          'name' => 'API Tokens View'],
            ['slug' => 'api_tokens.manage',        'name' => 'API Tokens Manage'],
            ['slug' => 'api_endpoints.view',       'name' => 'API Endpoints View'],
            ['slug' => 'api_endpoints.manage',     'name' => 'API Endpoints Manage'],
            ['slug' => 'api_docs.view',            'name' => 'API Docs View'],
            ['slug' => 'api_docs.manage',          'name' => 'API Docs Manage'],
            ['slug' => 'push_notifications.view',  'name' => 'Push Notifications View'],
            ['slug' => 'push_notifications.manage','name' => 'Push Notifications Manage'],
        ];

        foreach ($perms as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $perm['slug']],
                ['module' => 'dealership', 'name' => $perm['name'], 'created_at' => now()]
            );
        }
    }
}
