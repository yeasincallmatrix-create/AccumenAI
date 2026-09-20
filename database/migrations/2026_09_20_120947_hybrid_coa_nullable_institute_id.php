<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hybrid COA: allow NULL institute_id (global template rows) while
     * keeping code uniqueness. MySQL treats NULLs as distinct in unique
     * keys, so generated columns coalesce NULL→0 for uniqueness only.
     */
    public function up(): void
    {
        // Step 1: virtual generated columns for the unique key.
        DB::statement('
            ALTER TABLE chart_of_accounts
            ADD COLUMN institute_key BIGINT
                GENERATED ALWAYS AS (COALESCE(institute_id, 0)) VIRTUAL,
            ADD COLUMN branch_key BIGINT
                GENERATED ALWAYS AS (COALESCE(branch_id, 0)) VIRTUAL
        ');

        // Step 2: drop old unique key.
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropUnique('uq_coa_code');
        });

        // Step 3: make institute_id nullable.
        DB::statement('
            ALTER TABLE chart_of_accounts
            MODIFY COLUMN institute_id BIGINT UNSIGNED NULL
        ');

        // Step 4: new unique key over generated columns.
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->unique(
                ['institute_key', 'branch_key', 'code'],
                'uq_coa_code_v2'
            );
        });

        // Step 5: composite index for tenant+system queries.
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->index(
                ['institute_id', 'is_system', 'is_active'],
                'idx_coa_tenant_global'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex('idx_coa_tenant_global');
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropUnique('uq_coa_code_v2');
        });

        $nullCount = DB::table('chart_of_accounts')->whereNull('institute_id')->count();
        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Cannot rollback: {$nullCount} global rows exist. Delete them first."
            );
        }

        DB::statement('
            ALTER TABLE chart_of_accounts
            MODIFY COLUMN institute_id BIGINT UNSIGNED NOT NULL
        ');

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->unique(
                ['institute_id', 'branch_id', 'code'],
                'uq_coa_code'
            );
        });

        DB::statement('
            ALTER TABLE chart_of_accounts
            DROP COLUMN institute_key,
            DROP COLUMN branch_key
        ');
    }
};
