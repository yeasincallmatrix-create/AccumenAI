<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('is_principal')->default(false)->after('status');
        });

        // Backfill: mark the single branch as principal for each institute that has exactly one branch.
        $instituteIds = DB::table('branches')
            ->select('institute_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('institute_id')
            ->having('cnt', '=', 1)
            ->pluck('institute_id');

        foreach ($instituteIds as $instituteId) {
            DB::table('branches')
                ->where('institute_id', $instituteId)
                ->limit(1)
                ->update(['is_principal' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('is_principal');
        });
    }
};
