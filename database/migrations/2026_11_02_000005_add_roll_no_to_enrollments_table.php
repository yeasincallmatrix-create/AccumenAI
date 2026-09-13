<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add nullable column + unique constraint if not exists
        if (Schema::hasTable('enrollments') && !Schema::hasColumn('enrollments', 'roll_no')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->integer('roll_no')->nullable()->after('batch_id');
            });
        }

        // Add unique constraint on (institute_id, batch_id, roll_no) if not exists
        if (Schema::hasTable('enrollments') && Schema::hasColumn('enrollments', 'roll_no')) {
            $hasUnique = false;
            try {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                // Doctrine DBAL may not be available, fallback to raw check
                $indexes = $sm->listTableIndexes('enrollments');
                foreach ($indexes as $idx) {
                    if ($idx->getName() === 'enrollments_batch_roll_unique') {
                        $hasUnique = true;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Check via information_schema
                try {
                    $exists = DB::selectOne("SELECT COUNT(*) as c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'enrollments' AND INDEX_NAME = 'enrollments_batch_roll_unique'");
                    if ($exists && (int)$exists->c > 0) $hasUnique = true;
                } catch (\Throwable $e2) {}
            }

            if (! $hasUnique) {
                try {
                    Schema::table('enrollments', function (Blueprint $table) {
                        $table->unique(['institute_id', 'batch_id', 'roll_no'], 'enrollments_batch_roll_unique');
                    });
                } catch (\Throwable $e) {
                    // MySQL duplicate or already exists - ignore if backfill not done yet
                    // If there are existing nulls, unique will allow multiple nulls in MySQL, so it's safe
                    try {
                        DB::statement("CREATE UNIQUE INDEX enrollments_batch_roll_unique ON enrollments (institute_id, batch_id, roll_no)");
                    } catch (\Throwable $e2) {}
                }
            }
        }

        // Backfill existing enrollments per batch
        // Group by institute_id, batch_id and assign sequential roll_no starting at 1
        $batches = DB::table('enrollments')->select('institute_id', 'batch_id')->distinct()->get();
        foreach ($batches as $batch) {
            $enrollments = DB::table('enrollments')
                ->where('institute_id', $batch->institute_id)
                ->where('batch_id', $batch->batch_id)
                ->orderBy('id')
                ->get(['id', 'roll_no']);

            $roll = 1;
            foreach ($enrollments as $enr) {
                // Only fill if null to preserve admin-entered values if already set
                if ($enr->roll_no === null) {
                    // Find next available roll_no that doesn't collide (handles partial backfill)
                    while (DB::table('enrollments')
                        ->where('institute_id', $batch->institute_id)
                        ->where('batch_id', $batch->batch_id)
                        ->where('roll_no', $roll)
                        ->where('id', '!=', $enr->id)
                        ->exists()) {
                        $roll++;
                    }
                    DB::table('enrollments')->where('id', $enr->id)->update(['roll_no' => $roll]);
                } else {
                    // If roll_no already set, ensure next roll starts after max
                    $roll = max($roll, (int)$enr->roll_no + 1);
                    continue;
                }
                $roll++;
            }
        }

        // Make NOT NULL after backfill
        if (Schema::hasTable('enrollments') && Schema::hasColumn('enrollments', 'roll_no')) {
            try {
                Schema::table('enrollments', function (Blueprint $table) {
                    $table->integer('roll_no')->nullable(false)->change();
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement("ALTER TABLE `enrollments` MODIFY `roll_no` INT NOT NULL");
                } catch (\Throwable $e2) {
                    // Leave nullable if doctrine/dbal missing and raw fails (e.g., still nulls)
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('enrollments')) {
            // Drop unique index first
            try {
                Schema::table('enrollments', function (Blueprint $table) {
                    $table->dropUnique('enrollments_batch_roll_unique');
                });
            } catch (\Throwable $e) {
                try {
                    DB::statement("ALTER TABLE `enrollments` DROP INDEX `enrollments_batch_roll_unique`");
                } catch (\Throwable $e2) {}
            }

            if (Schema::hasColumn('enrollments', 'roll_no')) {
                Schema::table('enrollments', function (Blueprint $table) {
                    $table->dropColumn('roll_no');
                });
            }
        }
    }
};
