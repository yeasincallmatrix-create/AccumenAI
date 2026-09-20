<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hybrid COA (groups half): allow NULL institute_id so global
     * template groups can exist. Mirrors the chart_of_accounts
     * migration: generated key columns keep code uniqueness safe
     * (MySQL treats NULLs as distinct in unique keys).
     *
     * Order matters: the institute_id FK needs a leftmost-institute_id
     * index at all times, so the replacement index is added BEFORE the
     * old unique key is dropped (else MySQL error 1553).
     */
    public function up(): void
    {
        // Step 1: virtual generated columns for the unique key.
        DB::statement('
            ALTER TABLE account_groups
            ADD COLUMN institute_key BIGINT
                GENERATED ALWAYS AS (COALESCE(institute_id, 0)) VIRTUAL,
            ADD COLUMN branch_key BIGINT
                GENERATED ALWAYS AS (COALESCE(branch_id, 0)) VIRTUAL
        ');

        // Step 2: replacement index FIRST (keeps the institute_id FK
        // supported while the old unique key is dropped).
        Schema::table('account_groups', function (Blueprint $table) {
            $table->index(
                ['institute_id', 'is_system'],
                'idx_groups_tenant_global'
            );
        });

        // Step 3: drop old unique key.
        Schema::table('account_groups', function (Blueprint $table) {
            $table->dropUnique('uq_account_groups_code');
        });

        // Step 4: make institute_id nullable.
        DB::statement('
            ALTER TABLE account_groups
            MODIFY COLUMN institute_id BIGINT UNSIGNED NULL
        ');

        // Step 5: new unique key over generated columns.
        Schema::table('account_groups', function (Blueprint $table) {
            $table->unique(
                ['institute_key', 'branch_key', 'code'],
                'uq_account_groups_code_v2'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $nullCount = DB::table('account_groups')->whereNull('institute_id')->count();
        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Cannot rollback: {$nullCount} global groups exist."
            );
        }

        // Reverse order: restore the old unique key FIRST so the
        // institute_id FK stays supported throughout.
        Schema::table('account_groups', function (Blueprint $table) {
            $table->unique(
                ['institute_id', 'branch_id', 'code'],
                'uq_account_groups_code'
            );
        });

        Schema::table('account_groups', function (Blueprint $table) {
            $table->dropUnique('uq_account_groups_code_v2');
        });

        Schema::table('account_groups', function (Blueprint $table) {
            $table->dropIndex('idx_groups_tenant_global');
        });

        DB::statement('
            ALTER TABLE account_groups
            MODIFY COLUMN institute_id BIGINT UNSIGNED NOT NULL
        ');

        DB::statement('
            ALTER TABLE account_groups
            DROP COLUMN institute_key,
            DROP COLUMN branch_key
        ');
    }
};
