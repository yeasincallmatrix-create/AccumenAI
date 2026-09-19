<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 7 STEP 3F: approval_actions.approver_id was NOT NULL while
     * callers (web guard user()->id, tests with global users.id) cannot
     * always supply an institute_users.id. Nullable matches the sibling
     * *_by columns (created_by, requested_by, resolved_by). FK re-added
     * with identical rules (CASCADE on delete, RESTRICT on update).
     */
    public function up(): void
    {
        Schema::table('approval_actions', function (Blueprint $table) {
            $table->dropForeign(['approver_id']);
            $table->unsignedBigInteger('approver_id')->nullable()->change();
            $table->foreign('approver_id')->references('id')->on('institute_users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_actions', function (Blueprint $table) {
            $table->dropForeign(['approver_id']);
            $table->unsignedBigInteger('approver_id')->nullable(false)->change();
            $table->foreign('approver_id')->references('id')->on('institute_users')->onDelete('cascade');
        });
    }
};
