<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 107 — Data Archive System
 * Archives old records, keeps original IDs, supports restore. Never auto-deletes.
 */
class ArchiveService
{
    public const RULES = [
        'attendance' => ['years' => 3, 'table' => 'attendance', 'archive' => 'attendance_archive', 'date_col' => 'class_date'],
        'notifications' => ['years' => 1, 'table' => 'notifications', 'archive' => 'notifications_archive', 'date_col' => 'created_at'],
        'audit_logs' => ['years' => 5, 'table' => 'audit_logs', 'archive' => 'audit_logs_archive', 'date_col' => 'created_at'],
        'activity_logs' => ['years' => 5, 'table' => 'activity_logs', 'archive' => 'activity_logs_archive', 'date_col' => 'created_at'],
    ];

    public function archive(string $module, bool $dryRun = true): array
    {
        if (! isset(self::RULES[$module])) {
            throw new \InvalidArgumentException("Unknown archive module: $module");
        }

        $rule = self::RULES[$module];
        $cutoff = now()->subYears($rule['years'])->toDateString();

        $query = DB::table($rule['table'])->where($rule['date_col'], '<', $cutoff);
        $total = $query->count();

        if ($dryRun || $total === 0) {
            return [
                'module' => $module,
                'cutoff' => $cutoff,
                'total' => $total,
                'archived' => 0,
                'dry_run' => $dryRun,
            ];
        }

        $job = DB::table('archive_jobs')->insertGetId([
            'module' => $module,
            'status' => 'running',
            'total_rows' => $total,
            'archived_rows' => 0,
            'criteria' => json_encode(['cutoff' => $cutoff, 'date_col' => $rule['date_col']]),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $archived = 0;
        $chunkSize = 500;

        DB::table($rule['table'])
            ->where($rule['date_col'], '<', $cutoff)
            ->orderBy('id')
            ->chunk($chunkSize, function ($rows) use ($rule, &$archived) {
                foreach ($rows as $row) {
                    $data = (array)$row;
                    $originalId = $data['id'];
                    $originalCreated = $data['created_at'] ?? $data[$rule['date_col']] ?? null;

                    DB::table($rule['archive'])->insert([
                        'original_id' => $originalId,
                        'data' => json_encode($data),
                        'original_created_at' => $originalCreated,
                        'archived_at' => now(),
                    ]);
                    $archived++;
                }
            });

        // Do NOT delete originals automatically — archiving only
        // Deletion would be manual via separate approved workflow

        DB::table('archive_jobs')->where('id', $job)->update([
            'status' => 'completed',
            'archived_rows' => $archived,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'module' => $module,
            'cutoff' => $cutoff,
            'total' => $total,
            'archived' => $archived,
            'dry_run' => false,
            'job_id' => $job,
        ];
    }

    public function restore(string $module, array $ids): int
    {
        if (! isset(self::RULES[$module])) {
            throw new \InvalidArgumentException("Unknown module: $module");
        }

        $rule = self::RULES[$module];
        $restored = 0;

        $archives = DB::table($rule['archive'])->whereIn('original_id', $ids)->get();
        foreach ($archives as $arch) {
            $data = json_decode($arch->data, true);
            if (! $data) continue;

            // Restore by re-inserting into original table keeping original ID
            // Only if not already exists
            $exists = DB::table($rule['table'])->where('id', $data['id'])->exists();
            if (! $exists) {
                // Remove timestamps that may conflict
                DB::table($rule['table'])->insert($data);
                $restored++;
            }
        }

        return $restored;
    }

    public function stats(): array
    {
        $stats = [];
        foreach (self::RULES as $module => $rule) {
            $cutoff = now()->subYears($rule['years'])->toDateString();
            $total = DB::table($rule['table'])->where($rule['date_col'], '<', $cutoff)->count();
            $archived = DB::table($rule['archive'])->count();
            $stats[$module] = [
                'eligible' => $total,
                'archived' => $archived,
                'cutoff' => $cutoff,
            ];
        }
        return $stats;
    }
}
