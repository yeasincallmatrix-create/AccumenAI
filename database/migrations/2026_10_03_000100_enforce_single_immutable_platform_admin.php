<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single Immutable Super Admin - Database Protection
 *
 * Invariant: PLATFORM_SUPER_ADMIN_COUNT = EXACTLY 1
 * - id = 1, email = yeasinsheikh999@gmail.com, is_owner = 1
 * - Uses singleton_guard unique constraint compatible with MySQL
 *   (all rows singleton_guard=1 => unique prevents 2nd row)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Verify existing super admin before hardening
        $count = DB::table('platform_admins')->count();
        $owner = DB::table('platform_admins')->where('id', 1)->first();

        if ($count !== 1) {
            // Do not silently fix multi-row state; ensure exactly 1 before adding constraint
            // If >1 rows, migration will fail with explicit message - operator must resolve
            throw new RuntimeException(
                "Cannot enforce single super admin: expected 1 row in platform_admins, found {$count}. Resolve manually before migrating."
            );
        }

        if (! $owner) {
            throw new RuntimeException('Cannot enforce single super admin: platform_admins.id=1 not found.');
        }

        // Normalize canonical owner email if drifted (preserve id=1)
        $canonicalEmail = 'yeasinsheikh999@gmail.com';
        if (strtolower(trim($owner->email)) !== $canonicalEmail) {
            DB::table('platform_admins')->where('id', 1)->update([
                'email' => $canonicalEmail,
                'is_owner' => 1,
                'status' => 'active',
            ]);
        } elseif ((int) $owner->is_owner !== 1) {
            DB::table('platform_admins')->where('id', 1)->update(['is_owner' => 1]);
        }

        // Step 2: Add singleton_guard column with unique constraint
        if (! Schema::hasColumn('platform_admins', 'singleton_guard')) {
            Schema::table('platform_admins', function (Blueprint $table) {
                $table->tinyInteger('singleton_guard')->default(1)->nullable(false)->after('id');
            });
        }

        // Ensure existing row is 1
        DB::table('platform_admins')->where('id', 1)->update(['singleton_guard' => 1]);

        // Add unique index if not exists
        $indexes = collect(DB::select("SHOW INDEX FROM platform_admins"))->pluck('Key_name');
        if (! $indexes->contains('uq_platform_admins_singleton')) {
            Schema::table('platform_admins', function (Blueprint $table) {
                $table->unique('singleton_guard', 'uq_platform_admins_singleton');
            });
        }

        // Add helper unique on is_owner to prevent second owner (is_owner=1 must be unique via filtered approach)
        // MySQL lacks partial indexes, so we enforce via singleton_guard + is_owner invariant in app layer + check below
        // Verify final state
        $finalCount = DB::table('platform_admins')->count();
        $finalOwnerCount = DB::table('platform_admins')->where('is_owner', 1)->count();
        if ($finalCount !== 1 || $finalOwnerCount !== 1) {
            throw new RuntimeException("Post-migration invariant violated: count={$finalCount}, owner_count={$finalOwnerCount}");
        }
    }

    public function down(): void
    {
        Schema::table('platform_admins', function (Blueprint $table) {
            $indexes = collect(DB::select("SHOW INDEX FROM platform_admins"))->pluck('Key_name');
            if (in_array('uq_platform_admins_singleton', $indexes->toArray(), true)) {
                $table->dropUnique('uq_platform_admins_singleton');
            }
            if (Schema::hasColumn('platform_admins', 'singleton_guard')) {
                $table->dropColumn('singleton_guard');
            }
        });
    }
};
