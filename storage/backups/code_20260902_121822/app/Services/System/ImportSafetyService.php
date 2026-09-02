<?php

namespace App\Services\System;

use App\Models\ImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Step 109 — Import Safety Framework
 */
class ImportSafetyService
{
    public function startImport(string $module, string $fileName, array $rows, ?int $userId = null): ImportBatch
    {
        $token = Str::random(64);
        $batch = ImportBatch::create([
            'module' => $module,
            'file_name' => $fileName,
            'total_rows' => count($rows),
            'success_rows' => 0,
            'failed_rows' => 0,
            'rollback_token' => hash('sha256', $token),
            'status' => 'pending',
            'created_by' => $userId ?? auth()->id(),
        ]);
        // Store raw token as non-persisted attribute
        $batch->setAttribute('raw_token', $token);
        $batch->syncOriginal();

        // Store rows
        foreach ($rows as $i => $row) {
            $batch->rows()->create([
                'row_number' => $i + 1,
                'data' => $row,
                'status' => 'pending',
            ]);
        }

        return $batch;
    }

    public function processRow(ImportBatch $batch, int $rowNumber, callable $handler): bool
    {
        $row = $batch->rows()->where('row_number', $rowNumber)->first();
        if (! $row) return false;

        try {
            $handler($row->data);
            $row->update(['status' => 'success']);
            $batch->increment('success_rows');
            return true;
        } catch (\Throwable $e) {
            $row->update(['status' => 'failed', 'error' => $e->getMessage()]);
            $batch->increment('failed_rows');
            $errors = $batch->errors ?? [];
            $errors[] = "Row $rowNumber: ".$e->getMessage();
            $batch->update(['errors' => $errors]);
            return false;
        }
    }

    public function complete(ImportBatch $batch): ImportBatch
    {
        $status = $batch->failed_rows > 0 ? 'failed' : 'completed';
        $batch->update(['status' => $status]);
        return $batch->fresh();
    }

    public function rollback(ImportBatch $batch, string $token): bool
    {
        $hashed = hash('sha256', $token);
        if ($batch->rollback_token !== $hashed) {
            throw new \Exception('Invalid rollback token');
        }

        // Rollback: mark as rolled_back and optionally delete created records
        // For safety, we only mark rows as rolled back; actual deletion is module-specific
        DB::transaction(function () use ($batch) {
            // Example: for students, we would delete created students with this batch token
            // Here we just mark
            $batch->update(['status' => 'rolled_back']);
            $batch->rows()->where('status', 'success')->update(['status' => 'failed', 'error' => 'Rolled back']);
        });

        return true;
    }

    public function report(ImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'module' => $batch->module,
            'total' => $batch->total_rows,
            'success' => $batch->success_rows,
            'failed' => $batch->failed_rows,
            'status' => $batch->status,
            'errors' => $batch->errors ?? [],
        ];
    }

    public function errorReport(ImportBatch $batch): array
    {
        $rows = $batch->rows()->where('status', 'failed')->get();
        return [
            'batch' => $batch->id,
            'failed_rows' => $rows->count(),
            'errors' => $rows->map(fn($r) => ['row' => $r->row_number, 'error' => $r->error])->all(),
        ];
    }
}
