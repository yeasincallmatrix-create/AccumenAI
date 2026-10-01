<?php

namespace App\Services\Backup;

use App\Models\Backup;
use App\Models\BackupChunk;
use App\Models\BackupManifest;
use App\Models\TenantDriveConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManifestService
{
    public function __construct(
        private \App\Contracts\DriveStorageInterface $drive,
        private ChunkReferenceService $refService
    ) {}

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
     * Phase 2D — atomic manifest swap:
     *   validate → encrypt → DB transaction (manifest [+ chunks + refs +
     *   backup completed]) → Drive upload (after commit) → non-fatal
     *   Drive-ID persist. DB is the source of truth; current.json.enc is a
     *   convenience copy.
     *
     * @param array|null $tableData Chunked-path table entries — when given,
     *                              the transaction also covers chunk rows,
     *                              ref registration and backup completion.
     */
    public function saveManifest(
        Backup $backup,
        TenantDriveConnection $conn,
        array $manifest,
        EncryptionService $encryption,
        ?array $tableData = null
    ): BackupManifest {
        $jsonManifest = json_encode($manifest, JSON_PRETTY_PRINT);
        $manifestSha = hash('sha256', $jsonManifest);

        $phase = 'validate';
        $committed = false;

        // STEP 1: validation BEFORE any DB/Drive write
        if (!config('backup.validate_before_commit', true)) {
            return $this->writeManifestLegacy(
                $backup, $conn, $manifest, $jsonManifest, $manifestSha, $encryption
            );
        }
        $this->validateManifest($manifest, $jsonManifest, $manifestSha);

        // STEP 2: encrypt manifest to temp (chunk-temp dir)
        $phase = 'encrypt';
        $tmpDir = config('backup.chunk_temp_dir', storage_path('app/chunk-temp'));
        @mkdir($tmpDir, 0755, true);
        $tmp = "{$tmpDir}/manifest-{$backup->id}.json";
        file_put_contents($tmp, $jsonManifest);

        $encPath = $tmp . '.enc';
        $fileHmac = $encryption->encryptFile($tmp, $encPath, $backup->tenant_id);
        @unlink($tmp);

        try {
            // STEP 3: ensure Drive folders (API calls only — no data writes)
            $folders = $this->drive->ensureTenantFolderStructure($conn);

            $tableCount = count($manifest['tables']);
            $totalChunks = (int) collect($manifest['tables'])->sum(fn ($t) => count($t['chunks'] ?? []));
            $totalBytes = (int) collect($manifest['tables'])->sum('total_size');

            // STEP 4: DB TRANSACTION — all-or-nothing
            $phase = 'transaction';
            $record = DB::transaction(function () use (
                $backup, $conn, $manifest, $jsonManifest, $manifestSha,
                $fileHmac, $tableCount, $totalChunks, $totalBytes, $tableData
            ) {
                $rec = BackupManifest::updateOrCreate(
                    ['backup_id' => $backup->id],
                    [
                        'tenant_id'        => $backup->tenant_id,
                        'drive_file_id'    => null,   // filled after Drive upload
                        'checksum_file_id' => null,   // filled after Drive upload
                        'manifest_json'    => $jsonManifest,
                        'manifest_sha256'  => $manifestSha,
                        'file_hmac'        => $fileHmac,
                        'total_chunks'     => $totalChunks ?: $tableCount,
                        'total_size_bytes' => $totalBytes,
                    ]
                );

                // Chunked path: chunk rows + reference registration +
                // backup completion in the SAME transaction (no partial state)
                if ($tableData !== null) {
                    $chunkRowIds = [];
                    foreach ($tableData as $table => $data) {
                        foreach ($data['chunks'] ?? [] as $chunk) {
                            $chunkRowIds[] = BackupChunk::create([
                                'manifest_id'    => $rec->id,
                                'tenant_id'      => $backup->tenant_id,
                                'content_sha256' => $chunk['hash'],
                                'file_hmac'      => $chunk['file_hmac'],
                                'drive_file_id'  => $chunk['drive_file_id'],
                                'source_table'   => $table,
                                'chunk_index'    => $chunk['index'],
                                'total_chunks'   => $data['total_chunks'],
                                'size_bytes'     => $chunk['size'],
                            ])->id;
                        }
                    }

                    $this->refService->registerReferences($backup->tenant_id, $rec, $chunkRowIds);

                    $backup->update([
                        'status'           => 'completed',
                        'completed_at'     => now(),
                        'progress_percent' => 95,
                        'progress_stage'   => 'committed',
                        'progress_message' => 'Manifest committed',
                    ]);
                }

                return $rec;
            });
            $committed = true;

            // STEP 5: Drive upload AFTER commit (content-addressed → idempotent)
            $phase = 'drive-upload';
            $currentId = $this->drive->uploadFile(
                $conn, $encPath, 'current.json.enc', $folders['manifests']
            );
            $checksumId = $this->drive->uploadContent(
                $conn, $manifestSha, 'current.json.checksum', $folders['manifests']
            );
            $timestamped = 'manifest-' . now()->format('YmdHis') . '.enc';
            $this->drive->uploadFile(
                $conn, $encPath, $timestamped, $folders['manifest_backups']
            );

            // STEP 6: persist Drive IDs (second update — non-fatal if it fails;
            // DB row + chunk rows already committed)
            try {
                $record->update([
                    'drive_file_id'    => $currentId,
                    'checksum_file_id' => $checksumId,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Manifest Drive IDs not persisted (DB OK)', [
                    'manifest_id' => $record->id,
                    'error'       => $e->getMessage(),
                ]);
            }

            Log::info('Manifest saved atomically', [
                'tenant_id' => $backup->tenant_id,
                'backup_id' => $backup->id,
                'sha'       => substr($manifestSha, 0, 16),
                'chunks'    => $totalChunks,
            ]);

            return $record->fresh();
        } catch (\Throwable $e) {
            // Pre-commit failures (validate/encrypt/transaction) roll back
            // automatically; post-commit Drive failures leave the DB row as
            // source of truth (retry idempotent via backup_id UNIQUE).
            Log::error('Manifest save failed', [
                'backup_id' => $backup->id,
                'phase'     => $phase,
                'committed' => $committed,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            @unlink($encPath);
        }
    }

    /**
     * Phase 2D — validate manifest before commit. Throws on any failure.
     *
     * Order (Adaptation 1): required keys → table/chunk structure →
     * self-checksum → size sanity. Checksum is still enforced pre-commit;
     * this order yields precise error messages.
     */
    private function validateManifest(array $manifest, string $json, string $sha): void
    {
        // 1. Required keys
        foreach (['backup_id', 'tenant_id', 'created_at', 'tables'] as $key) {
            if (!array_key_exists($key, $manifest)) {
                throw new \RuntimeException("Manifest missing required key: {$key}");
            }
        }

        // 2. Tables structure — an EMPTY tables map is a legitimate backup of
        // a fresh tenant (zero rows); malformed/non-array is not.
        if (!is_array($manifest['tables'])) {
            throw new \RuntimeException("Manifest 'tables' must be an array");
        }

        foreach ($manifest['tables'] as $table => $info) {
            if (!isset($info['hash']) || !isset($info['chunks'])) {
                throw new \RuntimeException("Table {$table} missing hash or chunks");
            }

            foreach ($info['chunks'] as $i => $chunk) {
                foreach (['hash', 'size', 'drive_file_id'] as $field) {
                    if (!isset($chunk[$field])) {
                        throw new \RuntimeException("Table {$table} chunk {$i} missing {$field}");
                    }
                }
            }
        }

        // 3. Self-checksum
        if (!$this->verifyChecksum($manifest)) {
            throw new \RuntimeException('Manifest self-checksum failed');
        }

        // 4. JSON size sanity (< 10 MB — larger means something is wrong)
        $sizeMb = strlen($json) / 1048576;
        if ($sizeMb > 10) {
            throw new \RuntimeException("Manifest too large: {$sizeMb}MB (max 10MB)");
        }
    }

    /**
     * Legacy path (validate_before_commit = false) — 2A behavior preserved
     * verbatim: encrypt → Drive → DB row. No transaction, no validation.
     */
    private function writeManifestLegacy(
        Backup $backup,
        TenantDriveConnection $conn,
        array $manifest,
        string $jsonManifest,
        string $manifestSha,
        EncryptionService $encryption
    ): BackupManifest {
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
