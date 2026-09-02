<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;

/**
 * Step 124-H — Database Capacity Monitoring (recommendation only)
 */
class DatabaseCapacityService
{
    public function metrics(): array
    {
        $dbName = DB::getDatabaseName();
        $size = 0;
        $indexSize = 0;
        $largest = [];
        $growth = null;

        try {
            $row = DB::selectOne("SELECT SUM(data_length + index_length) as total, SUM(index_length) as idx FROM information_schema.TABLES WHERE table_schema = ?", [$dbName]);
            $size = $row->total ?? 0;
            $indexSize = $row->idx ?? 0;

            $largestRows = DB::select("SELECT table_name, (data_length + index_length) as total, data_length, index_length, table_rows FROM information_schema.TABLES WHERE table_schema = ? ORDER BY total DESC LIMIT 5", [$dbName]);
            foreach ($largestRows as $r) {
                $largest[] = ['table' => $r->table_name, 'size' => $r->total, 'data_size' => $r->data_length, 'index_size' => $r->index_length, 'rows' => $r->table_rows];
            }
        } catch (\Throwable $e) {}

        // Backup size
        $backupSize = 0;
        $backupGrowth = null;
        try {
            $backupSize = DB::table('system_backups')->sum('size_bytes');
        } catch (\Throwable $e) {}

        // Archive candidates (from ArchiveService)
        $archiveCandidates = [];
        try {
            $archiveCandidates = app(ArchiveService::class)->stats();
        } catch (\Throwable $e) {}

        return [
            'database_size' => $size,
            'table_size' => $size - $indexSize,
            'index_size' => $indexSize,
            'largest_tables' => $largest,
            'growth_rate' => $growth,
            'high_growth_tables' => [],
            'archive_candidates' => $archiveCandidates,
            'backup_size' => $backupSize,
            'backup_growth' => $backupGrowth,
        ];
    }
}
