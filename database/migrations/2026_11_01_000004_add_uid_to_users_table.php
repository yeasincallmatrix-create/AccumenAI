<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add uid column if not exists
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'uid')) {
            Schema::table('users', function (Blueprint $table) {
                // 6-char alphanumeric uppercase, unique platform-wide identifier
                $table->string('uid', 6)->nullable()->unique()->after('uuid');
            });
        }

        // Backfill existing rows where uid is null
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'uid')) {
            $users = DB::table('users')->whereNull('uid')->get(['id']);

            foreach ($users as $user) {
                $uid = $this->generateUniqueUid();
                // Retry on unique constraint race (unlikely but safe)
                $attempts = 0;
                while ($attempts < 10) {
                    try {
                        DB::table('users')->where('id', $user->id)->whereNull('uid')->update(['uid' => $uid]);
                        break;
                    } catch (\Illuminate\Database\QueryException $e) {
                        // Collision on unique index — regenerate
                        if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'duplicate')) {
                            $uid = $this->generateUniqueUid();
                            $attempts++;
                            continue;
                        }
                        throw $e;
                    }
                }
            }

            // Ensure no nulls remain — if any collision left null, fill again
            $remaining = DB::table('users')->whereNull('uid')->count();
            if ($remaining > 0) {
                foreach (DB::table('users')->whereNull('uid')->get(['id']) as $user) {
                    DB::table('users')->where('id', $user->id)->update(['uid' => $this->generateUniqueUid()]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'uid')) {
            // Drop unique index first if exists
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropUnique(['uid']);
                });
            } catch (\Throwable $e) {
                // Index may have different name — try generic drop
                try {
                    DB::statement('ALTER TABLE `users` DROP INDEX `users_uid_unique`');
                } catch (\Throwable $e2) {}
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('uid');
            });
        }
    }

    /**
     * Generate a 6-character alphanumeric uppercase UID unique across platform tables.
     */
    private function generateUniqueUid(): string
    {
        // Prefer helper if available (platform-wide uniqueness)
        if (function_exists('generate_platform_uid')) {
            return generate_platform_uid();
        }

        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 100;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $uid = '';
            for ($i = 0; $i < 6; $i++) {
                $uid .= $chars[random_int(0, 35)];
            }

            if (! $this->uidExists($uid)) {
                return $uid;
            }
        }

        throw new \RuntimeException('Unable to generate unique UID after ' . $maxAttempts . ' attempts');
    }

    private function uidExists(string $uid): bool
    {
        // Check users table directly
        if (DB::table('users')->where('uid', $uid)->exists()) {
            return true;
        }

        // Platform-wide uniqueness: check other tables that may hold a uid column
        $tables = ['institute_users', 'institution_user', 'guardians', 'platform_admins', 'platform_staffs', 'students'];

        foreach ($tables as $table) {
            try {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'uid')) {
                    continue;
                }
                if (DB::table($table)->where('uid', $uid)->exists()) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Ignore missing tables/columns during migration
                continue;
            }
        }

        return false;
    }
};
