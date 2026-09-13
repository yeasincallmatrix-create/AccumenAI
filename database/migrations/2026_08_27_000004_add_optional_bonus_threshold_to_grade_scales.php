<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_scales', function (Blueprint $table) {
            if (!Schema::hasColumn('grade_scales', 'optional_subject_bonus_threshold')) {
                $table->decimal('optional_subject_bonus_threshold', 4, 2)->default(2.00)->after('optional_subject_gpa');
            }
            if (!Schema::hasColumn('grade_scales', 'optional_subject_bonus_enabled')) {
                $table->boolean('optional_subject_bonus_enabled')->default(true)->after('optional_subject_bonus_threshold');
            }
            if (!Schema::hasColumn('grade_scales', 'max_gpa')) {
                $table->decimal('max_gpa', 4, 2)->default(5.00)->after('optional_subject_bonus_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('grade_scales', function (Blueprint $table) {
            if (Schema::hasColumn('grade_scales', 'optional_subject_bonus_threshold')) {
                $table->dropColumn('optional_subject_bonus_threshold');
            }
            if (Schema::hasColumn('grade_scales', 'optional_subject_bonus_enabled')) {
                $table->dropColumn('optional_subject_bonus_enabled');
            }
            if (Schema::hasColumn('grade_scales', 'max_gpa')) {
                $table->dropColumn('max_gpa');
            }
        });
    }
};
