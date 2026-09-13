<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('batches', 'academic_year_id')) {
            Schema::table('batches', function (Blueprint $table) {
                $table->foreignId('academic_year_id')->nullable()->after('course_id')->constrained('academic_years')->nullOnDelete();
                $table->index(['institute_id', 'academic_year_id'], 'batches_institute_year_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex('batches_institute_year_idx');
            $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
