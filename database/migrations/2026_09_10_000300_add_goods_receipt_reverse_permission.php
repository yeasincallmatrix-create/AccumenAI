<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $exists = DB::table('permissions')->where('slug', 'goods_receipt.reverse')->exists();
        if (! $exists) {
            DB::table('permissions')->insert([
                ['slug' => 'goods_receipt.reverse', 'name' => 'Reverse Goods Receipts', 'module' => 'purchase', 'created_at' => $now],
            ]);
        }
        $permId = DB::table('permissions')->where('slug', 'goods_receipt.reverse')->value('id');
        foreach (['institute-owner', 'institute-admin'] as $roleSlug) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if ($roleId && $permId) {
                $exists = DB::table('role_permissions')->where('role_id', $roleId)->where('permission_id', $permId)->exists();
                if (! $exists) {
                    DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permId]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'goods_receipt.reverse')->delete();
    }
};
