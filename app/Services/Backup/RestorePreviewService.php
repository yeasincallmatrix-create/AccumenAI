<?php

namespace App\Services\Backup;

use App\Models\BackupManifest;
use App\Models\RestorePreview;
use App\Models\TenantDriveConnection;
use Illuminate\Support\Facades\Log;

/**
 * Dry-run preview of a restore: downloads + decrypts every table in the
 * manifest and runs the time-aware diff WITHOUT touching any row.
 */
class RestorePreviewService
{
    public const MODES = ['merge', 'smart'];

    public function __construct(
        private BackupChunkService $chunkService,
        private ManifestService $manifestService,
        private SmartDiffCalculator $diffCalculator
    ) {}

    public function compute(int $tenantId, int $backupId, int $userId, string $mode): RestorePreview
    {
        if (!in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException("Invalid restore mode: {$mode}");
        }

        $manifest = BackupManifest::where('backup_id', $backupId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $manifestArr = json_decode($manifest->manifest_json, true);

        if (!is_array($manifestArr) || empty($manifestArr['created_at'])) {
            throw new \RuntimeException('Manifest missing created_at — cannot run time-aware diff');
        }

        if (!$this->manifestService->verifyChecksum($manifestArr)) {
            throw new \RuntimeException('Manifest checksum invalid — refusing preview');
        }

        $conn = TenantDriveConnection::where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->first();

        if (!$conn) {
            throw new \RuntimeException('Google Drive connection not found. Reconnect Drive first.');
        }

        $backupTimestamp = $manifestArr['created_at'];
        $tables = $manifestArr['tables'] ?? [];

        $diff = [];
        $failed = [];

        // One table at a time — never hold the whole snapshot in memory.
        foreach (array_keys($tables) as $table) {
            try {
                $json = $this->chunkService->downloadTableChunksParallel($manifest, $table, $conn);
                $rows = json_decode($json, true);

                if (!is_array($rows)) {
                    continue;
                }

                $tableDiff = $this->diffCalculator->computeDiff(
                    $tenantId,
                    [$table => ['decrypted_rows' => $rows]],
                    $backupTimestamp
                );

                foreach ($tableDiff as $name => $info) {
                    $diff[$name] = $info;
                }
            } catch (\Throwable $e) {
                $failed[$table] = $e->getMessage();
                Log::warning('Restore preview: table load failed', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($failed) && empty($diff)) {
            throw new \RuntimeException(
                'Preview failed — could not read any table from this backup: '
                . reset($failed)
            );
        }

        $preview = RestorePreview::create([
            'tenant_id'         => $tenantId,
            'backup_id'         => $backupId,
            'user_id'           => $userId,
            'mode'              => $mode,
            'diff'              => $diff + (empty($failed) ? [] : ['_failed' => $failed]),
            'total_insert'      => (int) collect($diff)->sum('insert'),
            'total_update'      => (int) collect($diff)->sum('update'),
            'total_soft_delete' => (int) collect($diff)->sum('delete'),
            'total_kept'        => (int) collect($diff)->sum('kept'),
            'expires_at'        => now()->addMinutes(30),
        ]);

        Log::info('Restore preview computed', [
            'preview_id'  => $preview->id,
            'tenant_id'   => $tenantId,
            'backup_id'   => $backupId,
            'mode'        => $mode,
            'insert'      => $preview->total_insert,
            'update'      => $preview->total_update,
            'soft_delete' => $preview->total_soft_delete,
            'kept'        => $preview->total_kept,
            'tables'      => count($diff),
            'failed'      => count($failed),
        ]);

        return $preview;
    }
}
