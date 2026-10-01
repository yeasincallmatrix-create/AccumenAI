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

    /** @var array<string, array{name:string, tenant_id:int, parent:?string}> */
    public array $folders = [];

    /** When true, uploadBackup() throws (simulates Drive outage / token failure). */
    public bool $failUpload = false;

    /** When true, downloadBackup() throws (simulates revoked connection). */
    public bool $failDownload = false;

    public int $uploads = 0;
    public int $deletes = 0;
    public int $folderCreates = 0;
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

    /*
    |----------------------------------------------------------------------
    | Phase 2A primitives (in-memory)
    |----------------------------------------------------------------------
    */

    public function ensureTenantFolderStructure(TenantDriveConnection $conn): array
    {
        if (!$conn->app_folder_id) {
            $conn->update(['app_folder_id' => $this->createFolder($conn, 'AccumenAI_' . $conn->tenant_id, null)]);
        }
        foreach (['chunks_folder_id' => 'chunks', 'manifests_folder_id' => 'manifests', 'trash_folder_id' => 'trash'] as $field => $name) {
            if (!$conn->{$field}) {
                $conn->update([$field => $this->createFolder($conn, $name, $conn->app_folder_id)]);
            }
        }

        $manifestBackups = $this->findOrCreateFolder('backups', $conn->manifests_folder_id, $conn);
        $conn->refresh();

        return [
            'app'              => $conn->app_folder_id,
            'chunks'           => $conn->chunks_folder_id,
            'manifests'        => $conn->manifests_folder_id,
            'trash'            => $conn->trash_folder_id,
            'manifest_backups' => $manifestBackups,
        ];
    }

    public function createFolder(TenantDriveConnection $conn, string $name, ?string $parentId): string
    {
        $id = 'fake-folder-' . ++$this->seq;
        $this->folders[$id] = ['name' => $name, 'tenant_id' => $conn->tenant_id, 'parent' => $parentId];
        $this->folderCreates++;

        return $id;
    }

    public function uploadFile(TenantDriveConnection $conn, string $localPath, string $remoteName, string $parentFolderId): string
    {
        if ($this->failUpload) {
            throw new \RuntimeException('Drive upload failed: fake outage');
        }
        if (!is_file($localPath)) {
            throw new \RuntimeException("Drive upload failed: local file missing: {$localPath}");
        }

        return $this->storeContent($conn, file_get_contents($localPath), $remoteName, $parentFolderId);
    }

    public function uploadContent(TenantDriveConnection $conn, string $content, string $remoteName, string $parentFolderId): string
    {
        if ($this->failUpload) {
            throw new \RuntimeException('Drive upload failed: fake outage');
        }

        return $this->storeContent($conn, $content, $remoteName, $parentFolderId);
    }

    public function downloadContent(TenantDriveConnection $conn, string $driveFileId): string
    {
        if ($this->failDownload) {
            throw new \RuntimeException('Drive download failed: fake outage');
        }
        if (!isset($this->files[$driveFileId])) {
            throw new \RuntimeException("Drive download failed: no such file: {$driveFileId}");
        }

        return $this->files[$driveFileId]['content'];
    }

    public function downloadFile(TenantDriveConnection $conn, string $driveFileId, string $localPath): void
    {
        $content = $this->downloadContent($conn, $driveFileId);

        @mkdir(dirname($localPath), 0755, true);
        file_put_contents($localPath, $content);
    }

    public function listFiles(TenantDriveConnection $conn, string $folderId): array
    {
        $out = [];
        foreach ($this->files as $id => $file) {
            if (($file['parent'] ?? null) === $folderId) {
                $out[] = (object) ['id' => $id, 'name' => $file['name'], 'size' => strlen($file['content'])];
            }
        }

        return $out;
    }

    private function storeContent(TenantDriveConnection $conn, string $content, string $remoteName, string $parentFolderId): string
    {
        $id = 'fake-file-' . ++$this->seq;
        $this->files[$id] = [
            'name'      => $remoteName,
            'tenant_id' => $conn->tenant_id,
            'content'   => $content,
            'parent'    => $parentFolderId,
        ];
        $this->uploads++;

        return $id;
    }

    private function findOrCreateFolder(string $name, ?string $parentId, TenantDriveConnection $conn): string
    {
        foreach ($this->folders as $id => $folder) {
            if ($folder['name'] === $name && $folder['parent'] === $parentId) {
                return $id;
            }
        }

        return $this->createFolder($conn, $name, $parentId);
    }
}
