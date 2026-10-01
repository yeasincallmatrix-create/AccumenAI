<?php

namespace App\Contracts;

use App\Models\TenantDriveConnection;

/**
 * Storage destination for encrypted backup files.
 *
 * Production binding: GoogleDriveService (AppServiceProvider).
 * Test binding:       tests/Support/FakeDriveService.
 */
interface DriveStorageInterface
{
    /**
     * Get or create the tenant's App Folder. Returns the folder id.
     */
    public function getOrCreateAppFolder(TenantDriveConnection $conn): string;

    /**
     * Upload an encrypted backup file. Returns the remote file id.
     */
    public function uploadBackup(
        TenantDriveConnection $conn,
        string $localPath,
        string $filename
    ): string;

    /**
     * Download a remote backup file to $localPath.
     */
    public function downloadBackup(
        TenantDriveConnection $conn,
        string $driveFileId,
        string $localPath
    ): void;

    /**
     * Delete a remote backup file. Must not throw on failure.
     */
    public function deleteFile(TenantDriveConnection $conn, string $driveFileId): void;

    /**
     * List backup files in the tenant's App Folder.
     *
     * @return array<int, object>
     */
    public function listBackups(TenantDriveConnection $conn): array;
}
