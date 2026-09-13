<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Institute-level subject customization columns on `institute_subjects`.
 *
 * Rows are created ONLY when an institute customizes a subject (override or
 * custom addition) — an untouched institute has zero rows for inherited
 * subjects and simply inherits the global assignments.
 *
 *   - name          → institute-specific display name (null = inherit global)
 *   - display_order → institute-specific ordering (null = inherit assignment order)
 *   - status        → 'active' / 'inactive' (inactive = disabled for this institute)
 *   - is_custom     → true = institute-created subject (source "custom")
 *
 * A row with status='active' but no name/display_order carries no visible
 * customization; it exists to mark an institute-created subject or to re-enable
 * a subject that was previously disabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institute_subjects', function (Blueprint $table) {
            if (! Schema::hasColumn('institute_subjects', 'name')) {
                $table->string('name', 120)->nullable()->after('subject_id');
            }
            if (! Schema::hasColumn('institute_subjects', 'display_order')) {
                $table->unsignedInteger('display_order')->nullable()->after('name');
            }
            if (! Schema::hasColumn('institute_subjects', 'status')) {
                $table->string('status', 20)->default('active')->after('display_order');
            }
            if (! Schema::hasColumn('institute_subjects', 'is_custom')) {
                $table->boolean('is_custom')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('institute_subjects', function (Blueprint $table) {
            foreach (['is_custom', 'status', 'display_order', 'name'] as $column) {
                if (Schema::hasColumn('institute_subjects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
