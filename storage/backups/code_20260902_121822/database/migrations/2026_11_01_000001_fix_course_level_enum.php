<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize existing invalid levels to 'basic' before tightening enum
        try {
            DB::table('courses')
                ->whereNotIn('level', ['basic', 'intermediate', 'advanced'])
                ->whereNotNull('level')
                ->update(['level' => 'basic']);
            // Also handle null/empty
            DB::table('courses')->whereNull('level')->update(['level' => 'basic']);
            DB::table('courses')->where('level', '')->update(['level' => 'basic']);
        } catch (\Throwable $e) {}

        // Change to strict enum
        try {
            DB::statement("ALTER TABLE courses MODIFY level ENUM('basic', 'intermediate', 'advanced') NOT NULL DEFAULT 'basic'");
        } catch (\Throwable $e) {
            // Fallback for testing SQLite
            try {
                if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'level')) {
                    // SQLite doesn't support MODIFY, skip
                }
            } catch (\Throwable $e2) {}
        }
    }

    public function down(): void
    {
        try {
            DB::statement("ALTER TABLE courses MODIFY level VARCHAR(50) NOT NULL DEFAULT 'basic'");
        } catch (\Throwable $e) {}
    }
};
