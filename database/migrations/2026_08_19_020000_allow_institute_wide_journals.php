<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 32 — allow whole-institute journal postings.
 *
 * The journals / journal_entries tables were originally created with a NOT NULL
 * branch_id so every posting was strictly branch-scoped. Owners and institute
 * admins act at the whole-institute level (BranchContext disabled), so their
 * postings must be able to carry branch_id = NULL (institute-wide), matching the
 * BranchScopedOrShared convention used across the accounting models.
 *
 * Also changes the branch FK from cascadeOnDelete to nullOnDelete: deleting a
 * branch must never silently destroy its financial records.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journals')) {
            return;
        }

        Schema::table('journals', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->unsignedBigInteger('branch_id')->nullable()->change();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->unsignedBigInteger('branch_id')->nullable()->change();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('journals')) {
            return;
        }

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::table('journals', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });
    }
};
