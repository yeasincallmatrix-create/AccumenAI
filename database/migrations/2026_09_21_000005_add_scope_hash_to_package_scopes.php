<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add nullable scope_hash column
        Schema::table('package_scopes', function (Blueprint $table) {
            $table->string('scope_hash', 120)
                ->nullable()
                ->after('status');
        });

        // Step 2: Backfill existing rows
        DB::table('package_scopes')->update([
            'scope_hash' => DB::raw(
                "CONCAT(COALESCE(CAST(`package_id` AS CHAR CHARSET utf8mb4),'G'),'-',COALESCE(CAST(`country_id` AS CHAR CHARSET utf8mb4),'G'),'-',COALESCE(CAST(`industry_id` AS CHAR CHARSET utf8mb4),'G'),'-',COALESCE(CAST(`sub_industry_id` AS CHAR CHARSET utf8mb4),'G'))"
            ),
        ]);

        // Step 3: Make non-nullable
        Schema::table('package_scopes', function (Blueprint $table) {
            $table->string('scope_hash', 120)->nullable(false)->change();
        });

        // Step 4: Drop old broken unique + add scope_hash unique
        // MySQL InnoDB won't drop uq_package_scope via Laravel because it thinks
        // an FK depends on it (even though package_scoped_features FK references id).
        // Use FK_CHECKS=0 to safely drop.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('ALTER TABLE `package_scopes` DROP INDEX `uq_package_scope`');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Schema::table('package_scopes', function (Blueprint $table) {
            $table->unique('scope_hash', 'uq_package_scope_hash');
        });
    }

    public function down(): void
    {
        Schema::table('package_scopes', function (Blueprint $table) {
            $table->dropUnique('uq_package_scope_hash');
            $table->dropColumn('scope_hash');
            $table->unique(
                ['package_id', 'country_id', 'industry_id', 'sub_industry_id'],
                'uq_package_scope'
            );
        });
    }
};
