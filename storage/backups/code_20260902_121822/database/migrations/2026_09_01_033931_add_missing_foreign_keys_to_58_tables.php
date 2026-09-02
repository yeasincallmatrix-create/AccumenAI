<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tables identified as missing FK to institutes (P1 — Critical).
     * Audit showed 9 remaining after prior hardening: activity_logs, ai_logs, ai_usage, audit_logs, email_otps, phone_2fa_otps, subject_requests, tenant_deletion_requests, tenant_recovery_archives.
     * All have institute_id column but no foreign key constraint.
     */
    private array $tables = [
        ['table' => 'activity_logs', 'nullable' => true, 'onDelete' => 'nullOnDelete'],
        ['table' => 'ai_logs', 'nullable' => true, 'onDelete' => 'nullOnDelete'],
        ['table' => 'ai_usage', 'nullable' => false, 'onDelete' => 'cascadeOnDelete'],
        ['table' => 'audit_logs', 'nullable' => true, 'onDelete' => 'nullOnDelete'], // historical: preserve via NULL, not CASCADE; alternatively restrictOnDelete
        ['table' => 'email_otps', 'nullable' => true, 'onDelete' => 'cascadeOnDelete'],
        ['table' => 'phone_2fa_otps', 'nullable' => true, 'onDelete' => 'cascadeOnDelete'],
        ['table' => 'subject_requests', 'nullable' => false, 'onDelete' => 'cascadeOnDelete'],
        ['table' => 'tenant_deletion_requests', 'nullable' => true, 'onDelete' => 'cascadeOnDelete'],
        ['table' => 'tenant_recovery_archives', 'nullable' => true, 'onDelete' => 'cascadeOnDelete'],
    ];

    public function up(): void
    {
        // Sanitize orphans before adding FK (prevents constraint failure)
        // Set orphan institute_id to NULL where column is nullable, delete where NOT NULL and orphan
        foreach ($this->tables as $item) {
            $table = $item['table'];
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'institute_id')) {
                continue;
            }
            // Check for orphans: institute_id not in institutes
            try {
                $orphans = DB::table($table)
                    ->leftJoin('institutes', 'institutes.id', '=', $table . '.institute_id')
                    ->whereNull('institutes.id')
                    ->whereNotNull($table . '.institute_id')
                    ->count();
                if ($orphans > 0) {
                    if ($item['nullable']) {
                        DB::table($table)
                            ->leftJoin('institutes', 'institutes.id', '=', $table . '.institute_id')
                            ->whereNull('institutes.id')
                            ->whereNotNull($table . '.institute_id')
                            ->update([$table . '.institute_id' => null]);
                    } else {
                        // NOT NULL case: delete orphans (data meaningless without institute)
                        DB::table($table)
                            ->leftJoin('institutes', 'institutes.id', '=', $table . '.institute_id')
                            ->whereNull('institutes.id')
                            ->whereNotNull($table . '.institute_id')
                            ->delete();
                    }
                }
            } catch (\Throwable $e) {
                // Continue even if check fails
            }
        }

        // Add FKs
        foreach ($this->tables as $item) {
            $table = $item['table'];
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'institute_id')) {
                continue;
            }
            // Skip if FK already exists
            try {
                $exists = DB::selectOne(
                    "SELECT COUNT(*) as cnt FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'institute_id' AND REFERENCED_TABLE_NAME = 'institutes'",
                    [$table]
                );
                if ($exists && (int)$exists->cnt > 0) {
                    continue;
                }
            } catch (\Throwable $e) {}

            try {
                Schema::table($table, function (Blueprint $tableBlueprint) use ($item) {
                    $onDelete = $item['onDelete'];
                    $foreign = $tableBlueprint->foreign('institute_id')->references('id')->on('institutes');
                    if ($onDelete === 'nullOnDelete') {
                        $foreign->nullOnDelete();
                    } elseif ($onDelete === 'cascadeOnDelete') {
                        $foreign->cascadeOnDelete();
                    } else {
                        $foreign->restrictOnDelete();
                    }
                });
            } catch (\Throwable $e) {
                // Log but don't block other tables
                \Illuminate\Support\Facades\Log::warning("Failed to add FK for {$item['table']}.institute_id: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $item) {
            $table = $item['table'];
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'institute_id')) {
                continue;
            }
            try {
                Schema::table($table, function (Blueprint $tableBlueprint) {
                    $tableBlueprint->dropForeign(['institute_id']);
                });
            } catch (\Throwable $e) {
                // Try dropping by known constraint name
                try {
                    $constraint = $table . '_institute_id_foreign';
                    DB::statement("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`");
                } catch (\Throwable $e2) {}
            }
        }
    }
};
