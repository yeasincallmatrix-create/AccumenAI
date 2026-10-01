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

    /*
    |--------------------------------------------------------------------------
    | Phase 2A: multi-folder structure + content-addressed primitives
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure the tenant's folder tree exists:
     * AccumenAI_<id>/{chunks,manifests,manifests/backups,trash}
     *
     * @return array{app:?string, chunks:?string, manifests:?string, trash:?string, manifest_backups:?string}
     */
    public function ensureTenantFolderStructure(TenantDriveConnection $conn): array;

    /**
     * Create a Drive folder. Returns the new folder id.
     */
    public function createFolder(TenantDriveConnection $conn, string $name, ?string $parentId): string;

    /**
     * Upload a local file into a specific folder. Returns Drive file id.
     */
    public function uploadFile(
        TenantDriveConnection $conn,
        string $localPath,
        string $remoteName,
        string $parentFolderId
    ): string;

    /**
     * Upload raw string content into a specific folder. Returns Drive file id.
     */
    public function uploadContent(
        TenantDriveConnection $conn,
        string $content,
        string $remoteName,
        string $parentFolderId
    ): string;

    /**
     * Download a Drive file and return its contents as a string.
     */
    public function downloadContent(TenantDriveConnection $conn, string $driveFileId): string;

    /**
     * Download a Drive file to a local path.
     */
    public function downloadFile(TenantDriveConnection $conn, string $driveFileId, string $localPath): void;

    /**
     * List files directly inside a folder.
     *
     * @return array<int, object>
     */
    public function listFiles(TenantDriveConnection $conn, string $folderId): array;
}
