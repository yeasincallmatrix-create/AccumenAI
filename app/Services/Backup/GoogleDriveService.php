<?php

namespace App\Services\Backup;

use App\Contracts\DriveStorageInterface;
use App\Mail\NotificationMail;
use App\Models\TenantDriveConnection;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GoogleDriveService implements DriveStorageInterface
{
    private const APP_FOLDER_NAME = 'AccumenAI';
    private const SCOPE = GoogleDrive::DRIVE_FILE;

    /**
     * Build authenticated Google client from tenant connection.
     * Auto-refreshes the access token; on refresh failure the connecting
     * user is emailed before the exception propagates.
     */
    private function clientFor(TenantDriveConnection $conn): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->addScope(self::SCOPE);
        $client->setAccessType('offline');

        // Exchange refresh token for access token
        $token = $client->fetchAccessTokenWithRefreshToken($conn->refresh_token);

        if (isset($token['error'])) {
            $this->notifyTokenFailure($conn, is_string($token['error']) ? $token['error'] : json_encode($token));
            throw new \RuntimeException(
                'Google Drive authorization expired or was revoked. Please reconnect Drive.'
            );
        }

        return $client;
    }

    /**
     * Design decision: token refresh failure → email the user who connected.
     * Never throws: notification failure must not mask the original error.
     */
    private function notifyTokenFailure(TenantDriveConnection $conn, string $error): void
    {
        try {
            $email = $conn->google_user_email;
            if (empty($email)) {
                return;
            }

            Mail::to($email)->send(new NotificationMail(
                'Google Drive connection needs attention',
                "Your AccumenAI backup connection to Google Drive could not be refreshed.\n\n"
                . "Institute (tenant): {$conn->tenant_id}\n"
                . "Reason: {$error}\n\n"
                . "Automatic backups will fail until you reconnect Google Drive:\n"
                . "Settings > Backup > Connect Drive."
            ));
        } catch (\Throwable $e) {
            Log::warning('drive_token_failure_email_failed', [
                'tenant_id' => $conn->tenant_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get or create tenant's App Folder in Drive.
     */
    public function getOrCreateAppFolder(TenantDriveConnection $conn): string
    {
        $client = $this->clientFor($conn);
        $drive = new GoogleDrive($client);

        // Check if folder already known
        if ($conn->drive_folder_id) {
            try {
                $drive->files->get($conn->drive_folder_id, ['fields' => 'id']);
                return $conn->drive_folder_id;
            } catch (\Throwable $e) {
                // Folder gone → recreate
                Log::info('Drive folder missing, recreating', [
                    'tenant_id' => $conn->tenant_id,
                    'folder_id' => $conn->drive_folder_id,
                ]);
            }
        }

        // Create folder
        $file = new DriveFile([
            'name'     => self::APP_FOLDER_NAME . '_' . $conn->tenant_id,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        $created = $drive->files->create($file, ['fields' => 'id']);

        $conn->update(['drive_folder_id' => $created->id]);

        return $created->id;
    }

    /**
     * Upload encrypted backup file. Returns Drive file ID.
     */
    public function uploadBackup(
        TenantDriveConnection $conn,
        string $localPath,
        string $filename
    ): string {
        $client = $this->clientFor($conn);
        $drive = new GoogleDrive($client);

        $folderId = $this->getOrCreateAppFolder($conn);

        $fileMetadata = new DriveFile([
            'name'    => $filename,
            'parents' => [$folderId],
        ]);

        $content = file_get_contents($localPath);

        $uploaded = $drive->files->create($fileMetadata, [
            'data'       => $content,
            'mimeType'   => 'application/octet-stream',
            'uploadType' => 'multipart',
            'fields'     => 'id, size, md5Checksum',
        ]);

        Log::info('Backup uploaded to Drive', [
            'tenant_id'  => $conn->tenant_id,
            'file_id'    => $uploaded->id,
            'size'       => $uploaded->size,
            'md5'        => $uploaded->md5Checksum,
        ]);

        return $uploaded->id;
    }

    /**
     * Download backup file to local path.
     */
    public function downloadBackup(
        TenantDriveConnection $conn,
        string $driveFileId,
        string $localPath
    ): void {
        $client = $this->clientFor($conn);
        $drive = new GoogleDrive($client);

        $response = $drive->files->get($driveFileId, ['alt' => 'media']);

        @mkdir(dirname($localPath), 0755, true);
        file_put_contents($localPath, $response->getBody()->getContents());
    }

    /**
     * Delete a backup file from Drive. Never throws (cleanup must not fail
     * the surrounding backup/restore flow).
     */
    public function deleteFile(TenantDriveConnection $conn, string $driveFileId): void
    {
        try {
            $client = $this->clientFor($conn);
            $drive = new GoogleDrive($client);
            $drive->files->delete($driveFileId);

            Log::info('Drive file deleted', [
                'tenant_id' => $conn->tenant_id,
                'file_id'   => $driveFileId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Drive delete failed', [
                'file_id' => $driveFileId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * List existing backup files in App Folder.
     */
    public function listBackups(TenantDriveConnection $conn): array
    {
        $folderId = $conn->drive_folder_id;
        if (!$folderId) {
            return [];
        }

        $client = $this->clientFor($conn);
        $drive = new GoogleDrive($client);

        $results = $drive->files->listFiles([
            'q'      => "'{$folderId}' in parents and trashed = false",
            'fields' => 'files(id, name, size, createdTime)',
        ]);

        return $results->getFiles();
    }
}
