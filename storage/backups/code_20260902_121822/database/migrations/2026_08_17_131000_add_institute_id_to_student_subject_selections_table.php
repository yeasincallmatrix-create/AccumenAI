<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * institute_id on student_subject_selections so the TenantScoped global scope
 * (which qualifies the model's own institute_id column) applies to the table.
 * Kept nullable so any pre-migration rows survive; the placement service always
 * stamps the parent placement's institute.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('student_subject_selections', 'institute_id')) {
            Schema::table('student_subject_selections', function (Blueprint $table) {
                $table->foreignId('institute_id')->nullable()->after('id')->constrained('institutes')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_subject_selections', 'institute_id')) {
            Schema::table('student_subject_selections', function (Blueprint $table) {
                $table->dropConstrainedForeignId('institute_id');
            });
        }
    }
};
