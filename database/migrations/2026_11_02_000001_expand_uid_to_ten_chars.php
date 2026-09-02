<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand uid columns from 6 to 10 chars (non-destructive, backward-compatible)
        // Users
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'uid')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('uid', 10)->nullable()->change();
                });
            } catch (\Throwable $e) {
                try { DB::statement("ALTER TABLE `users` MODIFY `uid` VARCHAR(10) NULL"); } catch (\Throwable $e2) {}
            }
            // Backfill any legacy 6-char UIDs to 10-char if desired? Keep as-is for BC.
            // Optionally ensure all nulls are filled (should already be filled)
            $nullUsers = DB::table('users')->whereNull('uid')->get(['id']);
            foreach ($nullUsers as $user) {
                $uid = function_exists('generateUid') ? generateUid() : $this->fallbackUid();
                // ensure uniqueness via loop
                $attempts = 0;
                while ($attempts < 5 && DB::table('users')->where('uid', $uid)->exists()) {
                    $uid = function_exists('generateUid') ? generateUid() : $this->fallbackUid();
                    $attempts++;
                }
                DB::table('users')->where('id', $user->id)->update(['uid' => $uid]);
            }
            // Re-add unique index if missing (previous migration may have it, but length change may drop it on some DBs)
            try {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('users');
                $hasUnique = false;
                foreach ($indexes as $idx) {
                    if ($idx->isUnique() && in_array('uid', $idx->getColumns())) { $hasUnique = true; break; }
                }
                if (! $hasUnique) {
                    Schema::table('users', function (Blueprint $table) {
                        $table->unique('uid');
                    });
                }
            } catch (\Throwable $e) {
                try { DB::statement("CREATE UNIQUE INDEX users_uid_unique ON `users` (`uid`)"); } catch (\Throwable $e2) {}
            }
        }

        // Institutes
        if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'uid')) {
            try {
                Schema::table('institutes', function (Blueprint $table) {
                    $table->string('uid', 10)->nullable()->change();
                });
            } catch (\Throwable $e) {
                try { DB::statement("ALTER TABLE `institutes` MODIFY `uid` VARCHAR(10) NULL"); } catch (\Throwable $e2) {}
            }
            $nullInst = DB::table('institutes')->whereNull('uid')->get(['id']);
            foreach ($nullInst as $inst) {
                $uid = function_exists('generateUid') ? generateUid() : $this->fallbackUid();
                $attempts = 0;
                while ($attempts < 5 && DB::table('institutes')->where('uid', $uid)->exists()) {
                    $uid = function_exists('generateUid') ? generateUid() : $this->fallbackUid();
                    $attempts++;
                }
                DB::table('institutes')->where('id', $inst->id)->update(['uid' => $uid]);
            }
            try {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('institutes');
                $hasUnique = false;
                foreach ($indexes as $idx) {
                    if ($idx->isUnique() && in_array('uid', $idx->getColumns())) { $hasUnique = true; break; }
                }
                if (! $hasUnique) {
                    Schema::table('institutes', function (Blueprint $table) {
                        $table->unique('uid');
                    });
                }
            } catch (\Throwable $e) {
                try { DB::statement("CREATE UNIQUE INDEX institutes_uid_unique ON `institutes` (`uid`)"); } catch (\Throwable $e2) {}
            }
        }

        // Optionally upgrade existing 6-char UIDs to 10-char for spec compliance
        // We do NOT auto-upgrade to keep backward compatibility (spec says non-destructive).
        // New records will be 10-char; existing 6-char remain valid.
    }

    public function down(): void
    {
        // Do not shrink column automatically to avoid data loss if 10-char data exists
        // This down is intentionally no-op for safety; manual intervention required if downgrade needed.
    }

    private function fallbackUid(): string
    {
        $alphanumeric = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $firstTwo = str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        $lastTwo = str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
        $uid = '';
        for ($i = 0; $i < 6; $i++) {
            $uid .= $alphanumeric[random_int(0, strlen($alphanumeric) - 1)];
        }
        return $uid . $firstTwo . $lastTwo;
    }
};
