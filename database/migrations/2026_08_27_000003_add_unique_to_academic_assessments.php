<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preflight: check for existing duplicates
        $dups = DB::select("
            SELECT institute_id, academic_year_id, class_grade_id, COALESCE(academic_group_id, 0) as gkey, name, COUNT(*) as c
            FROM academic_assessments
            GROUP BY institute_id, academic_year_id, class_grade_id, gkey, name
            HAVING c > 1
        ");
        if (!empty($dups)) {
            $msg = "Duplicate academic assessments found: ".json_encode($dups);
            \Illuminate\Support\Facades\Log::warning('A2 duplicate preflight', ['dups' => $dups]);
            // Do not automatically delete; fail safely so operator can resolve
            throw new \RuntimeException("Cannot add unique constraint: duplicate assessments exist. Resolve: $msg");
        }

        // Add virtual group_key for NULL handling and unique index
        // Use DB::statement for MySQL virtual column
        try {
            // Add virtual column if not exists
            $hasGroupKey = Schema::hasColumn('academic_assessments', 'group_key');
            if (!$hasGroupKey) {
                DB::statement("ALTER TABLE academic_assessments ADD COLUMN group_key BIGINT UNSIGNED GENERATED ALWAYS AS (IFNULL(academic_group_id, 0)) VIRTUAL");
            }
            // Add unique index
            DB::statement("ALTER TABLE academic_assessments ADD UNIQUE INDEX uq_assessment_institute_year_class_group_name (institute_id, academic_year_id, class_grade_id, group_key, name)");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('A2 unique index failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE academic_assessments DROP INDEX uq_assessment_institute_year_class_group_name");
        } catch (\Throwable $e) {}
        try {
            if (Schema::hasColumn('academic_assessments', 'group_key')) {
                DB::statement("ALTER TABLE academic_assessments DROP COLUMN group_key");
            }
        } catch (\Throwable $e) {}
    }
};
