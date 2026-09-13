<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_registry')->insert([
            'key'         => 'vat',
            'name'        => 'VAT / Tax',
            'type'        => 'core',
            'description' => 'VAT & tax configuration, returns and compliance',
            'dependencies' => null,
            'sort_order'  => 11,
            'status'      => 'active',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Add vat to packages that already include accounting/finance
        $now = now();
        $inserts = [];

        $proId = DB::table('subscription_packages')->where('slug', 'professional')->value('id');
        $enterpriseId = DB::table('subscription_packages')->where('slug', 'enterprise')->value('id');

        if ($proId) {
            $inserts[] = ['package_id' => $proId, 'module_key' => 'vat', 'enabled' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        if ($enterpriseId) {
            $inserts[] = ['package_id' => $enterpriseId, 'module_key' => 'vat', 'enabled' => true, 'created_at' => $now, 'updated_at' => $now];
        }

        if ($inserts) {
            DB::table('package_modules')->insert($inserts);
        }
    }

    public function down(): void
    {
        DB::table('package_modules')->where('module_key', 'vat')->delete();
        DB::table('module_registry')->where('key', 'vat')->delete();
    }
};
