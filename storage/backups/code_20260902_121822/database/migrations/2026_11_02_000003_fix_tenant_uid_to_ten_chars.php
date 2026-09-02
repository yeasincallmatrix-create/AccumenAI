<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure column is varchar(10)
        if (Schema::hasTable('institutes') && Schema::hasColumn('institutes', 'uid')) {
            try {
                // Check current type via information_schema or try change
                Schema::table('institutes', function (Blueprint $table) {
                    $table->string('uid', 10)->nullable()->change();
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement("ALTER TABLE `institutes` MODIFY `uid` VARCHAR(10) NULL");
                } catch (\Throwable $e2) {}
            }
            // Re-ensure unique index exists
            try {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('institutes');
                $hasUnique = false;
                foreach ($indexes as $idx) {
                    if ($idx->isUnique() && in_array('uid', $idx->getColumns(), true)) {
                        $hasUnique = true;
                        break;
                    }
                }
                if (! $hasUnique) {
                    Schema::table('institutes', function (Blueprint $table) {
                        $table->unique('uid');
                    });
                }
            } catch (\Throwable $e) {
                try {
                    DB::statement("CREATE UNIQUE INDEX institutes_uid_unique ON `institutes` (`uid`)");
                } catch (\Throwable $e2) {}
            }
        }

        // Fix existing institute UIDs that are not exactly 10 chars
        // Use transaction to avoid partial updates
        DB::transaction(function () {
            // Fetch all institutes with uid not null and length !=10
            // Use raw query to handle both null and length check
            $institutes = DB::table('institutes')
                ->whereNotNull('uid')
                ->whereRaw('CHAR_LENGTH(uid) != 10')
                ->get(['id', 'uid']);

            foreach ($institutes as $inst) {
                $current = (string) $inst->uid;
                $len = strlen($current);
                $newUid = null;

                if ($len === 6) {
                    // Append 4-digit suffix per spec: firstTwo 00-99 + lastTwo 10-99 (tens !=0)
                    $attempts = 0;
                    do {
                        $firstTwo = str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
                        $lastTwo = str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
                        $suffix = $firstTwo . $lastTwo;
                        $newUid = $current . $suffix;
                        $attempts++;
                        // Ensure uniqueness across institutes
                        $exists = DB::table('institutes')->where('uid', $newUid)->where('id', '!=', $inst->id)->exists();
                        if ($attempts > 100) {
                            // Fallback to fully generated UID if collisions persist
                            $newUid = function_exists('generateUid') ? generateUid() : $this->fallbackTen();
                            $exists = DB::table('institutes')->where('uid', $newUid)->exists();
                            if (! $exists) break;
                        }
                    } while ($exists);
                } elseif ($len < 10 && $len > 0) {
                    // For any shorter than 10 (e.g., truncated), pad to 10 with spec suffix logic
                    $needed = 10 - $len;
                    $attempts = 0;
                    do {
                        if ($needed === 4) {
                            $firstTwo = str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
                            $lastTwo = str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
                            $suffix = $firstTwo . $lastTwo;
                        } else {
                            // Generic pad with random alphanumeric + numeric to reach 10
                            $suffix = '';
                            $alphanum = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                            for ($i=0; $i<$needed; $i++) {
                                // Ensure last 2 of final uid are numeric with tens !=0 - handle separately
                                if ($i >= $needed - 2) {
                                    // Will be overwritten by numeric suffix if needed==4, else random numeric 10-99 logic doesn't apply directly
                                    $suffix .= (string) random_int(0, 9);
                                } else {
                                    $suffix .= $alphanum[random_int(0, strlen($alphanum)-1)];
                                }
                            }
                            // If needed !=4, ensure last two tens !=0 by fixing
                            if ($needed >= 2) {
                                $lastTwo = str_pad((string) random_int(10, 99), 2, '0', STR_PAD_LEFT);
                                $suffix = substr($suffix, 0, $needed - 2) . $lastTwo;
                            }
                        }
                        $newUid = $current . $suffix;
                        $exists = DB::table('institutes')->where('uid', $newUid)->where('id', '!=', $inst->id)->exists();
                        $attempts++;
                        if ($attempts > 100) {
                            $newUid = function_exists('generateUid') ? generateUid() : $this->fallbackTen();
                            $exists = DB::table('institutes')->where('uid', $newUid)->exists();
                            if (! $exists) break;
                        }
                    } while ($exists);
                } else {
                    // Null or empty or length >10 - generate fresh 10-char UID
                    $attempts = 0;
                    do {
                        $newUid = function_exists('generateUid') ? generateUid() : $this->fallbackTen();
                        $exists = DB::table('institutes')->where('uid', $newUid)->where('id', '!=', $inst->id)->exists();
                        $attempts++;
                    } while ($exists && $attempts < 100);
                }

                if ($newUid && $newUid !== $current) {
                    DB::table('institutes')->where('id', $inst->id)->update(['uid' => $newUid]);
                }
            }

            // Also handle institutes with null uid
            $nullInstitutes = DB::table('institutes')->whereNull('uid')->get(['id']);
            foreach ($nullInstitutes as $inst) {
                $attempts = 0;
                do {
                    $newUid = function_exists('generateUid') ? generateUid() : $this->fallbackTen();
                    $exists = DB::table('institutes')->where('uid', $newUid)->exists();
                    $attempts++;
                } while ($exists && $attempts < 100);
                DB::table('institutes')->where('id', $inst->id)->update(['uid' => $newUid]);
            }
        });
    }

    public function down(): void
    {
        // Down is intentionally no-op to avoid truncating 10-char UIDs back to 6
        // Manual intervention required if downgrade needed
    }

    private function fallbackTen(): string
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
