<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        if (! Schema::hasColumn('students', 'user_id')) {
            Schema::table('students', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            });
        }

        // Add foreign key if not exists
        $hasFk = false;
        try {
            $fks = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME = 'users'");
            if (! empty($fks)) $hasFk = true;
        } catch (\Throwable $e) {}

        if (! $hasFk) {
            try {
                Schema::table('students', function (Blueprint $table) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                });
            } catch (\Throwable $e) {
                // If FK creation fails (e.g., MySQL strict), try raw
                try {
                    DB::statement("ALTER TABLE `students` ADD CONSTRAINT `students_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL");
                } catch (\Throwable $e2) {}
            }
        }

        // Backfill: link via email if exists, but keep null if no mapping
        // Use transaction for safety, no data loss
        DB::transaction(function () {
            $students = DB::table('students')->whereNull('user_id')->whereNotNull('email')->get(['id', 'email']);
            foreach ($students as $student) {
                if (empty($student->email)) continue;
                $user = DB::table('users')->where('email', $student->email)->first(['id']);
                if ($user) {
                    DB::table('students')->where('id', $student->id)->update(['user_id' => $user->id]);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('students') || ! Schema::hasColumn('students', 'user_id')) {
            return;
        }

        try {
            Schema::table('students', function (Blueprint $table) {
                try { $table->dropForeign(['user_id']); } catch (\Throwable $e) {}
            });
        } catch (\Throwable $e) {}

        try {
            DB::statement("ALTER TABLE `students` DROP FOREIGN KEY `students_user_id_foreign`");
        } catch (\Throwable $e) {}

        try {
            Schema::table('students', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        } catch (\Throwable $e) {
            try { DB::statement("ALTER TABLE `students` DROP COLUMN `user_id`"); } catch (\Throwable $e2) {}
        }
    }
};
