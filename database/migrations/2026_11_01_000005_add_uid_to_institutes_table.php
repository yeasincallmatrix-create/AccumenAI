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
        if (Schema::hasTable('institutes') && ! Schema::hasColumn('institutes', 'uid')) {
            Schema::table('institutes', function (Blueprint $table) {
                $table->string('uid', 6)->nullable()->unique()->after('id');
            });
        }

        // Generate UIDs for existing records
        if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'uid')) {
            $institutes = DB::table('institutes')->whereNull('uid')->get(['id']);
            foreach ($institutes as $institute) {
                $uid = function_exists('generateUniqueUid')
                    ? generateUniqueUid('institutes')
                    : $this->generateFallbackUid();

                $attempts = 0;
                while ($attempts < 10) {
                    try {
                        DB::table('institutes')->where('id', $institute->id)->whereNull('uid')->update(['uid' => $uid]);
                        break;
                    } catch (\Illuminate\Database\QueryException $e) {
                        if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'duplicate')) {
                            $uid = function_exists('generateUniqueUid')
                                ? generateUniqueUid('institutes')
                                : $this->generateFallbackUid();
                            $attempts++;
                            continue;
                        }
                        throw $e;
                    }
                }
            }

            // Fallback for any remaining nulls (race condition)
            $remaining = DB::table('institutes')->whereNull('uid')->count();
            if ($remaining > 0) {
                foreach (DB::table('institutes')->whereNull('uid')->get(['id']) as $institute) {
                    $uid = function_exists('generateUniqueUid')
                        ? generateUniqueUid('institutes')
                        : $this->generateFallbackUid();
                    DB::table('institutes')->where('id', $institute->id)->update(['uid' => $uid]);
                }
            }

            // Make the column not nullable after populating (if doctrine/dbal available)
            try {
                Schema::table('institutes', function (Blueprint $table) {
                    $table->string('uid', 6)->nullable(false)->change();
                });
            } catch (\Throwable $e) {
                // Fallback via raw SQL if change() requires doctrine/dbal
                try {
                    DB::statement("ALTER TABLE `institutes` MODIFY `uid` varchar(6) NOT NULL");
                } catch (\Throwable $e2) {
                    // Leave as nullable if alteration fails — data already backfilled and unique index exists
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'uid')) {
            try {
                Schema::table('institutes', function (Blueprint $table) {
                    $table->dropUnique(['uid']);
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement('ALTER TABLE `institutes` DROP INDEX `institutes_uid_unique`');
                } catch (\Throwable $e2) {}
            }

            Schema::table('institutes', function (Blueprint $table) {
                $table->dropColumn('uid');
            });
        }
    }

    private function generateFallbackUid(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charLen = strlen($characters);
        do {
            $uid = '';
            for ($i = 0; $i < 6; $i++) {
                $uid .= $characters[random_int(0, $charLen - 1)];
            }
        } while (DB::table('institutes')->where('uid', $uid)->exists());

        return $uid;
    }
};
