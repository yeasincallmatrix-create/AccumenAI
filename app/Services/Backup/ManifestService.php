<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\BackupManifest;
use App\Models\TenantDriveConnection;
use Illuminate\Support\Facades\Log;

class ManifestService
{
    public function __construct(private \App\Contracts\DriveStorageInterface $drive) {}

    /**
     * Build manifest array from per-table hash/chunk data.
     *
     * Format:
     * [
     *   'version'    => '2.0',
     *   'backup_id'  => N,
     *   'tenant_id'  => N,
     *   'created_at' => ISO8601,
     *   'tables'     => [
     *      'users' => [
     *         'hash'  => 'sha256-of-table-json',
     *         'chunks' => [
     *            ['index' => 1, 'hash' => '...', 'size' => N, 'drive_file_id' => '...'],
     *         ],
     *         'row_count'   => N,
     *         'total_size'  => N,
     *      ],
     *   ],
     *   '_checksum'  => 'sha256 of manifest WITHOUT this field',
     * ]
     */
    public function buildManifest(int $tenantId, int $backupId, array $tableData): array
    {
        $manifest = [
            'version'    => '2.0',
            'backup_id'  => $backupId,
            'tenant_id'  => $tenantId,
            'created_at' => now()->toIso8601String(),
            'tables'     => $tableData,
        ];

        $manifest['_checksum'] = hash('sha256', json_encode($manifest));

        return $manifest;
    }

    /**
     * Save manifest: local encrypt → Drive (current + checksum + timestamped
     * copy) → DB record. Returns the BackupManifest row.
     */
    public function saveManifest(
        Backup $backup,
        TenantDriveConnection $conn,
        array $manifest,
        EncryptionService $encryption
    ): BackupManifest {
        $jsonManifest = json_encode($manifest, JSON_PRETTY_PRINT);
        $manifestSha = hash('sha256', $jsonManifest);

        // Encrypt manifest locally (chunk-temp dir)
        $tmpDir = config('backup.chunk_temp_dir', storage_path('app/chunk-temp'));
        @mkdir($tmpDir, 0755, true);
        $tmp = "{$tmpDir}/manifest-{$backup->id}.json";
        file_put_contents($tmp, $jsonManifest);

        $encPath = $tmp . '.enc';
        $fileHmac = $encryption->encryptFile($tmp, $encPath, $backup->tenant_id);
        @unlink($tmp);

        try {
            $folders = $this->drive->ensureTenantFolderStructure($conn);

            // current.json.enc — primary manifest
            $currentId = $this->drive->uploadFile(
                $conn,
                $encPath,
                'current.json.enc',
                $folders['manifests']
            );

            // current.json.checksum — SHA256 of plaintext manifest
            $checksumId = $this->drive->uploadContent(
                $conn,
                $manifestSha,
                'current.json.checksum',
                $folders['manifests']
            );

            // manifests/backups/manifest-<ts>.enc — timestamped copy
            $timestamped = 'manifest-' . now()->format('YmdHis') . '.enc';
            $this->drive->uploadFile(
                $conn,
                $encPath,
                $timestamped,
                $folders['manifest_backups']
            );
        } finally {
            @unlink($encPath);
        }

        $tableCount = count($manifest['tables']);
        $totalChunks = (int) collect($manifest['tables'])->sum(fn ($t) => count($t['chunks'] ?? []));
        $totalBytes = (int) collect($manifest['tables'])->sum('total_size');

        $record = BackupManifest::updateOrCreate(
            ['backup_id' => $backup->id],
            [
                'tenant_id'        => $backup->tenant_id,
                'drive_file_id'    => $currentId,
                'checksum_file_id' => $checksumId,
                'manifest_json'    => $jsonManifest,
                'manifest_sha256'  => $manifestSha,
                'file_hmac'        => $fileHmac,
                'total_chunks'     => $totalChunks ?: $tableCount,
                'total_size_bytes' => $totalBytes,
            ]
        );

        Log::info('Manifest saved', [
            'tenant_id'  => $backup->tenant_id,
            'backup_id'  => $backup->id,
            'sha'        => substr($manifestSha, 0, 16),
            'size_bytes' => strlen($jsonManifest),
            'tables'     => $tableCount,
            'chunks'     => $totalChunks,
        ]);

        return $record;
    }

    /**
     * Load manifest for tenant's latest backup. DB first (fast), Drive
     * fallback if the DB copy fails checksum verification.
     */
    public function loadCurrentManifest(
        TenantDriveConnection $conn,
        EncryptionService $encryption
    ): ?array {
        $record = BackupManifest::where('tenant_id', $conn->tenant_id)
            ->orderByDesc('id')
            ->first();

        if ($record) {
            $manifest = json_decode($record->manifest_json, true);
            if (is_array($manifest) && $this->verifyChecksum($manifest)) {
                return $manifest;
            }
            Log::warning('DB manifest checksum failed — trying Drive', [
                'tenant_id' => $conn->tenant_id,
                'backup_id' => $record->backup_id,
            ]);
        }

        // Fallback: download current.json.enc from Drive
        if (!$record || !$record->drive_file_id) {
            return null;
        }

        try {
            $encPath = storage_path('app/chunk-temp/manifest-restore-' . $record->backup_id . '.enc');
            $decPath = str_replace('.enc', '.json', $encPath);
            @mkdir(dirname($encPath), 0755, true);

            $this->drive->downloadFile($conn, $record->drive_file_id, $encPath);

            if (!$record->file_hmac) {
                throw new \RuntimeException('Manifest file HMAC missing — cannot decrypt safely');
            }

            $encryption->decryptFile($encPath, $decPath, $conn->tenant_id, $record->file_hmac);

            $manifest = json_decode((string) @file_get_contents($decPath), true);

            @unlink($encPath);
            @unlink($decPath);

            if (is_array($manifest) && $this->verifyChecksum($manifest)) {
                return $manifest;
            }
        } catch (\Throwable $e) {
            Log::error('Drive manifest fetch failed', [
                'tenant_id' => $conn->tenant_id,
                'error'     => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Verify manifest self-checksum (excludes the _checksum field itself).
     */
    public function verifyChecksum(array $manifest): bool
    {
        $expected = $manifest['_checksum'] ?? null;
        if (!$expected || !is_string($expected)) {
            return false;
        }

        $copy = $manifest;
        unset($copy['_checksum']);
        $actual = hash('sha256', json_encode($copy));

        return hash_equals($expected, $actual);
    }
}
