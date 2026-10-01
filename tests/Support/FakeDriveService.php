<?php

namespace Tests\Support;

use App\Contracts\DriveStorageInterface;
use App\Models\TenantDriveConnection;

/**
 * In-memory Drive double for tests. No network, no Google account.
 *
 * Bind with:
 *   $this->app->instance(DriveStorageInterface::class, new FakeDriveService());
 */
class FakeDriveService implements DriveStorageInterface
{
    /** @var array<string, array{name:string, tenant_id:int, content:string}> */
    public array $files = [];

    /** When true, uploadBackup() throws (simulates Drive outage / token failure). */
    public bool $failUpload = false;

    /** When true, downloadBackup() throws (simulates revoked connection). */
    public bool $failDownload = false;

    public int $uploads = 0;
    public int $deletes = 0;
    private int $seq = 0;

    public function getOrCreateAppFolder(TenantDriveConnection $conn): string
    {
        return 'fake-folder-' . $conn->tenant_id;
    }

    public function uploadBackup(TenantDriveConnection $conn, string $localPath, string $filename): string
    {
        if ($this->failUpload) {
            throw new \RuntimeException('Drive upload failed: fake outage');
        }
        if (!is_file($localPath)) {
            throw new \RuntimeException("Drive upload failed: local file missing: {$localPath}");
        }

        $id = 'fake-file-' . ++$this->seq;
        $this->files[$id] = [
            'name'      => $filename,
            'tenant_id' => $conn->tenant_id,
            'content'   => file_get_contents($localPath),
        ];
        $this->uploads++;

        return $id;
    }

    public function downloadBackup(TenantDriveConnection $conn, string $driveFileId, string $localPath): void
    {
        if ($this->failDownload) {
            throw new \RuntimeException('Drive download failed: fake outage');
        }
        if (!isset($this->files[$driveFileId])) {
            throw new \RuntimeException("Drive download failed: no such file: {$driveFileId}");
        }

        @mkdir(dirname($localPath), 0755, true);
        file_put_contents($localPath, $this->files[$driveFileId]['content']);
    }

    public function deleteFile(TenantDriveConnection $conn, string $driveFileId): void
    {
        $this->deletes++;
        unset($this->files[$driveFileId]);
    }

    public function listBackups(TenantDriveConnection $conn): array
    {
        $out = [];
        foreach ($this->files as $id => $file) {
            if ($file['tenant_id'] !== $conn->tenant_id) {
                continue;
            }
            $out[] = (object) ['id' => $id, 'name' => $file['name'], 'size' => strlen($file['content'])];
        }

        return $out;
    }
}
