<?php

namespace Tests\Feature;

use App\Models\BackupChunk;
use App\Models\BackupManifest;
use App\Models\TenantDriveConnection;
use App\Services\Backup\BackupChunkService;
use App\Services\Backup\EncryptionService;
use App\Services\Backup\GoogleDriveService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\Support\FakeDriveService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ParallelDownloadTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT = 999701;

    protected function tearDown(): void
    {
        BackupChunk::where('tenant_id', self::TENANT)->delete();
        BackupManifest::where('tenant_id', self::TENANT)->delete();
        parent::tearDown();
    }

    /**
     * Adaptation 2: non-GoogleDriveService drive → sequential fallback,
     * same output as downloadTableChunks.
     */
    public function test_parallel_falls_back_to_sequential_for_fake_drive()
    {
        $fake = new FakeDriveService();
        $encryption = app(EncryptionService::class);

        [$manifest, $plain] = $this->makeEncryptedTable($fake, $encryption, '{"users":[1,2,3]}');

        $svc = new BackupChunkService($fake, $encryption);
        $conn = new TenantDriveConnection(['tenant_id' => self::TENANT]);

        $out = $svc->downloadTableChunksParallel($manifest, 'users', $conn);

        $this->assertSame($plain, $out);
    }

    /**
     * Adaptation 1 (two-phase): parallel ciphertext download via mocked
     * Google HTTP client → sequential decrypt + SHA256 verify + merge in
     * chunk_index order. Temp files cleaned afterwards.
     */
    public function test_parallel_two_phase_download_merges_in_order()
    {
        $encryption = app(EncryptionService::class);

        $plain1 = '{"rows":["alpha"]}';
        $plain2 = '{"rows":["bravo","charlie"]}';

        [$cipher1, $hmac1] = $this->encryptString($plain1, $encryption);
        [$cipher2, $hmac2] = $this->encryptString($plain2, $encryption);

        $manifest = BackupManifest::create([
            'backup_id'       => 9997011,
            'tenant_id'       => self::TENANT,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('a', 64),
        ]);

        $chunks = [];
        foreach ([[$plain1, $cipher1, $hmac1, 1, 'file-aaa'], [$plain2, $cipher2, $hmac2, 2, 'file-bbb']] as [$plain, $cipher, $hmac, $idx, $fileId]) {
            $chunks[] = BackupChunk::create([
                'manifest_id'     => $manifest->id,
                'tenant_id'       => self::TENANT,
                'content_sha256'  => hash('sha256', $plain),
                'file_hmac'       => $hmac,
                'drive_file_id'   => $fileId,
                'source_table'    => 'users',
                'chunk_index'     => $idx,
                'total_chunks'    => 2,
                'size_bytes'      => strlen($plain),
                'reference_count' => 0,
            ]);
        }

        // GoogleDriveService subclass: HTTP client backed by MockHandler
        // (responses queued in request order = chunk_index order).
        $google = new class extends GoogleDriveService {
            public array $mockResponses = [];

            public function getAuthenticatedHttpClient(TenantDriveConnection $conn): Client
            {
                $mock = new MockHandler($this->mockResponses);
                return new Client([
                    'handler'  => HandlerStack::create($mock),
                    'base_uri' => 'https://www.googleapis.com/drive/v3/',
                ]);
            }
        };
        $google->mockResponses = [
            new Response(200, [], $cipher1),
            new Response(200, [], $cipher2),
        ];

        $svc = new BackupChunkService($google, $encryption);
        $conn = new TenantDriveConnection(['tenant_id' => self::TENANT]);

        $out = $svc->downloadTableChunksParallel($manifest, 'users', $conn, 2);

        // Order preserved + both plaintexts verified and merged
        $this->assertSame($plain1 . $plain2, $out);

        // Temp files cleaned (Phase A ciphertexts + Phase B plaintexts)
        $leftover = glob(config('backup.chunk_temp_dir') . '/pdl-*') ?: [];
        $this->assertSame([], $leftover);
    }

    /**
     * Tampered ciphertext → decrypt/verify must throw (never a silent merge).
     */
    public function test_parallel_rejects_tampered_chunk()
    {
        $encryption = app(EncryptionService::class);

        $plain = '{"rows":["good"]}';
        [$cipher, $hmac] = $this->encryptString($plain, $encryption);

        $manifest = BackupManifest::create([
            'backup_id'       => 9997012,
            'tenant_id'       => self::TENANT,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('b', 64),
        ]);

        BackupChunk::create([
            'manifest_id'     => $manifest->id,
            'tenant_id'       => self::TENANT,
            'content_sha256'  => hash('sha256', $plain),
            'file_hmac'       => $hmac,
            'drive_file_id'   => 'file-tampered',
            'source_table'    => 'users',
            'chunk_index'     => 1,
            'total_chunks'    => 1,
            'size_bytes'      => strlen($plain),
            'reference_count' => 0,
        ]);

        $tampered = 'X' . substr($cipher, 1); // flip first byte

        $google = new class extends GoogleDriveService {
            public array $mockResponses = [];

            public function getAuthenticatedHttpClient(TenantDriveConnection $conn): Client
            {
                $mock = new MockHandler($this->mockResponses);
                return new Client([
                    'handler'  => HandlerStack::create($mock),
                    'base_uri' => 'https://www.googleapis.com/drive/v3/',
                ]);
            }
        };
        $google->mockResponses = [new Response(200, [], $tampered)];

        $svc = new BackupChunkService($google, $encryption);
        $conn = new TenantDriveConnection(['tenant_id' => self::TENANT]);

        $this->expectException(\Throwable::class);
        try {
            $svc->downloadTableChunksParallel($manifest, 'users', $conn);
        } finally {
            $leftover = glob(config('backup.chunk_temp_dir') . '/pdl-*') ?: [];
            $this->assertSame([], $leftover, 'temp files must be cleaned even on failure');
        }
    }

    public function test_download_concurrency_config_default()
    {
        $this->assertEquals(10, config('backup.download_concurrency'));
    }

    // ── helpers ────────────────────────────────────────────────────────

    /** Encrypt a string → [ciphertext, fileHmac]. */
    private function encryptString(string $plain, EncryptionService $encryption): array
    {
        $tmpDir = config('backup.chunk_temp_dir', storage_path('app/chunk-temp'));
        @mkdir($tmpDir, 0755, true);
        $src = "{$tmpDir}/test-src-" . uniqid() . '.json';
        $dst = "{$tmpDir}/test-enc-" . uniqid() . '.enc';
        file_put_contents($src, $plain);

        try {
            $hmac = $encryption->encryptFile($src, $dst, self::TENANT);
            return [(string) file_get_contents($dst), $hmac];
        } finally {
            @unlink($src);
            @unlink($dst);
        }
    }

    /** Create manifest + one chunk row + store ciphertext in the fake drive. */
    private function makeEncryptedTable(
        FakeDriveService $fake,
        EncryptionService $encryption,
        string $plain
    ): array {
        [$cipher, $hmac] = $this->encryptString($plain, $encryption);

        $manifest = BackupManifest::create([
            'backup_id'       => 9997013,
            'tenant_id'       => self::TENANT,
            'manifest_json'   => '{}',
            'manifest_sha256' => str_repeat('c', 64),
        ]);

        $fileId = $fake->uploadContent(
            new TenantDriveConnection(['tenant_id' => self::TENANT]),
            $cipher,
            'chunk-1.enc',
            'fake-chunks'
        );

        BackupChunk::create([
            'manifest_id'     => $manifest->id,
            'tenant_id'       => self::TENANT,
            'content_sha256'  => hash('sha256', $plain),
            'file_hmac'       => $hmac,
            'drive_file_id'   => $fileId,
            'source_table'    => 'users',
            'chunk_index'     => 1,
            'total_chunks'    => 1,
            'size_bytes'      => strlen($plain),
            'reference_count' => 0,
        ]);

        return [$manifest, $plain];
    }
}
