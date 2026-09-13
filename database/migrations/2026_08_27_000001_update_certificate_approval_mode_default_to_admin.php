<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill nulls to admin (new default) — keep explicit super_admin as-is
        try {
            DB::table('institute_settings')
                ->whereNull('certificate_approval_mode')
                ->update(['certificate_approval_mode' => 'admin']);
        } catch (\Throwable $e) {}

        // Change column default to admin for future rows
        try {
            Schema::table('institute_settings', function (Blueprint $table) {
                $table->string('certificate_approval_mode', 20)->default('admin')->change();
            });
        } catch (\Throwable $e) {
            // SQLite / fallback — ignore, PHP defaults already handle it
        }
    }

    public function down(): void
    {
        try {
            Schema::table('institute_settings', function (Blueprint $table) {
                $table->string('certificate_approval_mode', 20)->nullable()->default(null)->change();
            });
        } catch (\Throwable $e) {}
        // Do not revert data — leave admin values as-is on rollback
    }
};
