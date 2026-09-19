<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 decision: Option A for LabResult tenant scoping.
     *
     * LabResult had no institute_id, so the TenantScoped global scope
     * (where institute_id = context) cannot apply. Denormalize institute_id
     * from the parent order: backfill existing rows, then add the column
     * (nullable — mirrors the branch-fence legacy-NULL rule) with index + FK.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('lab_results', 'institute_id')) {
            Schema::table('lab_results', function (Blueprint $table) {
                $table->unsignedBigInteger('institute_id')->nullable()->after('lab_order_id');
                $table->index(['institute_id', 'status'], 'lab_results_institute_status_index');
                $table->foreign('institute_id')->references('id')->on('institutes')->onDelete('cascade');
            });

            // Backfill from parent orders (0 rows on both DBs at Phase 1 time;
            // kept for production safety).
            DB::statement('UPDATE lab_results lr JOIN lab_orders lo ON lo.id = lr.lab_order_id SET lr.institute_id = lo.institute_id WHERE lr.institute_id IS NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lab_results', 'institute_id')) {
            Schema::table('lab_results', function (Blueprint $table) {
                $table->dropForeign(['institute_id']);
                $table->dropIndex('lab_results_institute_status_index');
                $table->dropColumn('institute_id');
            });
        }
    }
};
