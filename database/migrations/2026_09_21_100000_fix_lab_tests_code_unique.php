<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix multi-tenant violation: lab_tests.code was globally unique,
     * preventing two institutes from using the same test code (e.g. CBC).
     * Pre-migration audit (Phase 1 Step 2) confirmed 0 cross-institute
     * collisions on both accumen_ai and monetix_test.
     */
    public function up(): void
    {
        $old = DB::select("SHOW INDEX FROM lab_tests WHERE Key_name = 'lab_tests_code_unique'");
        if (! empty($old)) {
            Schema::table('lab_tests', function (Blueprint $table) {
                $table->dropUnique('lab_tests_code_unique');
            });
        }

        $new = DB::select("SHOW INDEX FROM lab_tests WHERE Key_name = 'uniq_lab_tests_institute_code'");
        if (empty($new)) {
            Schema::table('lab_tests', function (Blueprint $table) {
                $table->unique(['institute_id', 'code'], 'uniq_lab_tests_institute_code');
            });
        }
    }

    public function down(): void
    {
        $new = DB::select("SHOW INDEX FROM lab_tests WHERE Key_name = 'uniq_lab_tests_institute_code'");
        if (! empty($new)) {
            Schema::table('lab_tests', function (Blueprint $table) {
                $table->dropUnique('uniq_lab_tests_institute_code');
            });
        }

        $old = DB::select("SHOW INDEX FROM lab_tests WHERE Key_name = 'lab_tests_code_unique'");
        if (empty($old)) {
            Schema::table('lab_tests', function (Blueprint $table) {
                $table->unique('code', 'lab_tests_code_unique');
            });
        }
    }
};
