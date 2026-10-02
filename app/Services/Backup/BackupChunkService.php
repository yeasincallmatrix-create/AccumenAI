<?php

namespace App\Services\Backup;

use App\Models\BackupChunk;
use App\Models\BackupManifest;
use App\Models\TenantDriveConnection;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class BackupChunkService
{
    public function __construct(
        private \App\Contracts\DriveStorageInterface $drive,
        private EncryptionService $encryption
    ) {}

    /**
     * Process one table's JSON file (streamed — never loads the whole table):
     * 1. Split into adaptive-size chunks (read sequentially from disk)
     * 2. Per chunk: SHA256 (plaintext) → dedup vs old manifest → upload if new
     * 3. Chunks are ENCRYPTED with the tenant DEK before upload; the file
     *    HMAC travels in the manifest so restore can decrypt + verify.
     *
     * @return array{table:string, hash:string, chunks:array, row_count:int, total_size:int, total_chunks:int}
     */
    public function processTable(
        int $tenantId,
        string $table,
        string $jsonFile,
        TenantDriveConnection $conn,
        string $chunksFolderId,
        ?BackupManifest $oldManifest = null
    ): array {
        if (!is_file($jsonFile)) {
            throw new \RuntimeException("Chunk source missing: {$jsonFile}");
        }

        $sizeBytes = (int) filesize($jsonFile);
        $chunkSize = $this->determineChunkSize($sizeBytes);
        $oldHashIndex = $this->buildOldHashIndex($oldManifest);

        $chunks = [];
        $tableHash = hash_file('sha256', $jsonFile);

        if ($chunkSize <= 0) {
            // Below single threshold — one chunk, whole file (< 5 MB)
            $data = (string) file_get_contents($jsonFile);
            $chunks[] = $this->handleChunk(
                $tenantId, $table, 1, 1, $data, $conn, $chunksFolderId, $oldHashIndex
            );
        } else {
            $totalChunks = (int) ceil($sizeBytes / $chunkSize);
            $fh = fopen($jsonFile, 'rb');
            if ($fh === false) {
                throw new \RuntimeException("Cannot read chunk source: {$jsonFile}");
            }

            try {
                for ($i = 1; $i <= $totalChunks; $i++) {
                    $piece = '';
                    while (!feof($fh) && strlen($piece) < $chunkSize) {
                        $buf = fread($fh, $chunkSize - strlen($piece));
                        if ($buf === false) {
                            break;
                        }
                        $piece .= $buf;
                    }
                    if ($piece === '') {
                        break;
                    }

                    $chunks[] = $this->handleChunk(
                        $tenantId, $table, $i, $totalChunks, $piece, $conn,
                        $chunksFolderId, $oldHashIndex
                    );
                }
            } finally {
                fclose($fh);
            }
        }

        return [
            'table'        => $table,
            'hash'         => $tableHash,
            'chunks'       => $chunks,
            'row_count'    => 0, // caller fills
            'total_size'   => $sizeBytes,
            'total_chunks' => count($chunks),
        ];
    }

    /**
     * Dedup-aware handling of one chunk: reuse old drive_file_id + HMAC when
     * the plaintext hash already exists, otherwise encrypt + upload fresh.
     *
     * @return array{index:int, hash:string, size:int, drive_file_id:string, file_hmac:string, reused:bool}
     */
    private function handleChunk(
        int $tenantId,
        string $table,
        int $index,
        int $total,
        string $chunkData,
        TenantDriveConnection $conn,
        string $chunksFolderId,
        array $oldHashIndex
    ): array {
        $chunkHash = hash('sha256', $chunkData);

        if (isset($oldHashIndex[$chunkHash])) {
            Log::info('Chunk reused (dedup)', [
                'table' => $table,
                'chunk' => $index,
                'hash'  => substr($chunkHash, 0, 16),
            ]);

            return [
                'index'         => $index,
                'hash'          => $chunkHash,
                'size'          => strlen($chunkData),
                'drive_file_id' => $oldHashIndex[$chunkHash]['drive_file_id'],
                'file_hmac'     => $oldHashIndex[$chunkHash]['file_hmac'],
                'reused'        => true,
            ];
        }

        [$driveFileId, $fileHmac] = $this->uploadChunk(
            $tenantId, $table, $index, $total, $chunkData, $conn, $chunksFolderId
        );

        return [
            'index'         => $index,
            'hash'          => $chunkHash,
            'size'          => strlen($chunkData),
            'drive_file_id' => $driveFileId,
            'file_hmac'     => $fileHmac,
            'reused'        => false,
        ];
    }

    /**
     * Encrypt one chunk with the tenant DEK and upload the ciphertext.
     * 3 attempts with exponential backoff; permanent failure throws.
     *
     * @return array{0:string, 1:string} [drive_file_id, file_hmac]
     */
    private function uploadChunk(
        int $tenantId,
        string $table,
        int $index,
        int $total,
        string $chunkData,
        TenantDriveConnection $conn,
        string $chunksFolderId
    ): array {
        $safeTable = preg_replace('/[^A-Za-z0-9_]/', '_', $table);
        $remoteName = sprintf('chunk-%s-%03d-of-%03d.enc', $safeTable, $index, $total);

        $tmpDir = config('backup.chunk_temp_dir', storage_path('app/chunk-temp'));
        @mkdir($tmpDir, 0755, true);
        $plainTmp = "{$tmpDir}/{$tenantId}-{$remoteName}";
        $encTmp = $plainTmp . '.enc';

        file_put_contents($plainTmp, $chunkData);

        try {
            // Tenant-DEK encryption (chunked GCM format) — returns file HMAC
            $fileHmac = $this->encryption->encryptFile($plainTmp, $encTmp, $tenantId);

            $lastError = null;
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                try {
                    $fileId = $this->drive->uploadFile($conn, $encTmp, $remoteName, $chunksFolderId);

                    return [$fileId, $fileHmac];
                } catch (\Throwable $e) {
                    $lastError = $e;
                    Log::warning("Chunk upload attempt {$attempt} failed", [
                        'table' => $table,
                        'index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                    if ($attempt < 3) {
                        sleep(2 ** $attempt);
                    }
                }
            }

            throw new \RuntimeException(
                "Chunk {$table}#{$index} upload failed: " . ($lastError?->getMessage() ?? 'unknown error')
            );
        } finally {
            @unlink($plainTmp);
            @unlink($encTmp);
        }
    }

    /**
     * Adaptive chunk size by payload size. 0 = single chunk (no split).
     */
    private function determineChunkSize(int $bytes): int
    {
        $singleThreshold = (int) config('backup.chunk_single_threshold_mb', 5) * 1048576;
        $small = (int) config('backup.chunk_size_small_mb', 5) * 1048576;
        $medium = (int) config('backup.chunk_size_medium_mb', 20) * 1048576;
        $large = (int) config('backup.chunk_size_large_mb', 50) * 1048576;

        if ($bytes < $singleThreshold) {
            return 0;              // single chunk
        }
        if ($bytes < 100 * 1048576) {
            return $small;         // < 100 MB → 5 MB
        }
        if ($bytes < 500 * 1048576) {
            return $medium;        // < 500 MB → 20 MB
        }

        return $large;             // >= 500 MB → 50 MB
    }

    /**
     * Build content hash → [drive_file_id, file_hmac] index from the previous
     * manifest's stored JSON (dedup source — survives keep-1 row cleanup).
     *
     * @return array<string, array{drive_file_id:string, file_hmac:string}>
     */
    private function buildOldHashIndex(?BackupManifest $oldManifest): array
    {
        if (!$oldManifest) {
            return [];
        }

        $manifest = json_decode($oldManifest->manifest_json, true);
        if (!is_array($manifest)) {
            return [];
        }

        $index = [];
        foreach (($manifest['tables'] ?? []) as $tableData) {
            foreach (($tableData['chunks'] ?? []) as $chunk) {
                $hash = $chunk['hash'] ?? null;
                $fileId = $chunk['drive_file_id'] ?? null;
                $hmac = $chunk['file_hmac'] ?? null;
                if ($hash && $fileId && $hmac) {
                    $index[$hash] = ['drive_file_id' => $fileId, 'file_hmac' => $hmac];
                }
            }
        }

        return $index;
    }

    /**
     * Download + decrypt + verify + merge chunks for one table.
     * Each chunk's plaintext SHA256 must match the manifest before merging.
     */
    public function downloadTableChunks(
        BackupManifest $manifest,
        string $table,
        TenantDriveConnection $conn
    ): string {
        $chunks = BackupChunk::where('manifest_id', $manifest->id)
            ->where('source_table', $table)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            if ($this->manifestDeclaresNoChunks($manifest, $table)) {
                return '[]';   // zero rows at backup time — no payload by design
            }
            throw new \RuntimeException("No chunks for table {$table}");
        }

        $merged = '';
        $tmpDir = config('backup.chunk_temp_dir', storage_path('app/chunk-temp'));
        @mkdir($tmpDir, 0755, true);

        foreach ($chunks as $chunk) {
            if (!$chunk->file_hmac) {
                throw new \RuntimeException("Chunk {$chunk->id} missing file HMAC — refusing restore");
            }

            // Ciphertext from Drive → decrypt with stored HMAC → verify plaintext hash
            $ciphertext = $this->drive->downloadContent($conn, $chunk->drive_file_id);

            $encTmp = "{$tmpDir}/dl-{$chunk->id}.enc";
            $plainTmp = "{$tmpDir}/dl-{$chunk->id}.json";
            file_put_contents($encTmp, $ciphertext);

            try {
                $this->encryption->decryptFile($encTmp, $plainTmp, $manifest->tenant_id, $chunk->file_hmac);
                $plain = (string) file_get_contents($plainTmp);
            } finally {
                @unlink($encTmp);
                @unlink($plainTmp);
            }

            $actual = hash('sha256', $plain);
            if (!hash_equals($chunk->content_sha256, $actual)) {
                throw new \RuntimeException("Chunk {$chunk->id} checksum mismatch — refusing restore");
            }

            $merged .= $plain;
        }

        return $merged;
    }

    /**
     * Zero-row tables are recorded in the manifest with `chunks => []` and
     * `row_count => 0` (BackupService), so an absent chunk set for such a
     * table is normal — NOT data loss. Anything else still fails loudly.
     */
    private function manifestDeclaresNoChunks(BackupManifest $manifest, string $table): bool
    {
        $arr = json_decode((string) $manifest->manifest_json, true);
        $entry = is_array($arr) ? ($arr['tables'][$table] ?? null) : null;

        return is_array($entry)
            && (int) ($entry['row_count'] ?? -1) === 0
            && empty($entry['chunks'] ?? []);
    }

    /**
     * Phase 2C — download one table's chunks in PARALLEL (Adaptation 1,
     * two-phase; order-preserving; checksum-verified):
     *
     *   Phase A (network-bound): Guzzle Pool downloads ciphertexts with
     *     N concurrency into temp files (never whole payloads in memory).
     *   Phase B (CPU-bound): sequential in chunk_index order — decrypt with
     *     stored HMAC, SHA256(plaintext) verify vs content_sha256, append.
     *
     * Adaptation 2: concrete-only client — any non-GoogleDriveService drive
     * (FakeDriveService in tests) falls back to the sequential path.
     */
    public function downloadTableChunksParallel(
        BackupManifest $manifest,
        string $table,
        TenantDriveConnection $conn,
        int $concurrency = 0
    ): string {
        if (!$this->drive instanceof GoogleDriveService) {
            return $this->downloadTableChunks($manifest, $table, $conn);
        }

        $concurrency = $concurrency > 0
            ? $concurrency
            : (int) config('backup.download_concurrency', 10);

        $chunks = BackupChunk::where('manifest_id', $manifest->id)
            ->where('source_table', $table)
            ->orderBy('chunk_index')
            ->get();

        if ($chunks->isEmpty()) {
            if ($this->manifestDeclaresNoChunks($manifest, $table)) {
                return '[]';   // zero rows at backup time — no payload by design
            }
            throw new \RuntimeException("No chunks for table {$table}");
        }

        $tmpDir = config('backup.chunk_temp_dir', storage_path('app/chunk-temp'));
        @mkdir($tmpDir, 0755, true);

        // ── Phase A: parallel ciphertext download → temp files ──────────
        $client = $this->drive->getAuthenticatedHttpClient($conn);
        $encPaths = [];
        $failures = [];

        $requests = function () use ($chunks) {
            foreach ($chunks as $index => $chunk) {
                yield $index => new Request(
                    'GET',
                    'files/' . $chunk->drive_file_id . '?alt=media'
                );
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled'   => function (ResponseInterface $response, $index) use ($chunks, &$encPaths, $tmpDir) {
                $chunk = $chunks[$index];
                $path = "{$tmpDir}/pdl-{$chunk->id}.enc";
                file_put_contents($path, (string) $response->getBody());
                $encPaths[$chunk->id] = $path;
            },
            'rejected'    => function ($reason, $index) use ($chunks, &$failures) {
                $chunk = $chunks[$index];
                $failures[] = "chunk {$chunk->chunk_index}: " . (string) $reason;
                Log::error('Parallel chunk download failed', [
                    'chunk_id' => $chunk->id,
                    'reason'   => (string) $reason,
                ]);
            },
        ]);

        $pool->promise()->wait();

        if (!empty($failures)) {
            foreach ($encPaths as $p) {
                @unlink($p);
            }
            throw new \RuntimeException(
                'Parallel download failed for ' . count($failures) . ' chunk(s): ' . $failures[0]
            );
        }

        // ── Phase B: sequential decrypt + verify + merge (index order) ──
        $merged = '';
        try {
            foreach ($chunks as $chunk) {
                if (!isset($encPaths[$chunk->id])) {
                    throw new \RuntimeException("Missing downloaded chunk {$chunk->chunk_index}");
                }
                if (!$chunk->file_hmac) {
                    throw new \RuntimeException("Chunk {$chunk->id} missing file HMAC — refusing restore");
                }

                $plainTmp = "{$tmpDir}/pdl-{$chunk->id}.json";
                try {
                    $this->encryption->decryptFile(
                        $encPaths[$chunk->id], $plainTmp,
                        $manifest->tenant_id, $chunk->file_hmac
                    );
                    $plain = (string) file_get_contents($plainTmp);
                } finally {
                    @unlink($plainTmp);
                }

                $actual = hash('sha256', $plain);
                if (!hash_equals($chunk->content_sha256, $actual)) {
                    throw new \RuntimeException("Chunk {$chunk->id} checksum mismatch — refusing restore");
                }

                $merged .= $plain;
            }
        } finally {
            foreach ($encPaths as $p) {
                @unlink($p);
            }
        }

        return $merged;
    }
}
