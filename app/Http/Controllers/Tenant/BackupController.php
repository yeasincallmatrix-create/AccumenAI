<?php

namespace App\Http\Controllers\Tenant;

use App\Contracts\DriveStorageInterface;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\TenantDriveConnection;
use App\Services\Backup\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function __construct(
        private BackupService $service,
        private DriveStorageInterface $drive
    ) {}

    public function index()
    {
        $tenantId = auth()->user()->institute_id;
        $backups = Backup::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('tenant.backup.index', compact('backups'));
    }

    public function store(Request $request)
    {
        try {
            $backup = $this->service->createBackup(
                auth()->user()->institute_id,
                auth()->id()
            );

            return back()->with('success',
                "Backup completed — " . number_format($backup->size_bytes / 1048576, 2) . " MB");

        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function download(int $id)
    {
        $backup = Backup::where('tenant_id', auth()->user()->institute_id)->findOrFail($id);

        // G6: local .enc is deleted after a successful Drive upload, so pull
        // the ciphertext back from Drive when that is where it lives.
        if ($backup->destination === 'drive' && $backup->drive_file_id) {
            $conn = TenantDriveConnection::where('tenant_id', $backup->tenant_id)
                ->whereNull('revoked_at')
                ->first();

            if (!$conn) {
                abort(404, 'Google Drive connection not found for this backup.');
            }

            $tmpPath = storage_path("app/downloads/{$backup->filename}");
            @mkdir(dirname($tmpPath), 0755, true);

            try {
                $this->drive->downloadBackup($conn, $backup->drive_file_id, $tmpPath);
            } catch (\Throwable $e) {
                abort(404, 'Could not download backup from Google Drive: ' . $e->getMessage());
            }

            return response()->download($tmpPath, $backup->filename)
                ->deleteFileAfterSend(true);
        }

        $path = storage_path("app/backups/{$backup->filename}");
        abort_unless(file_exists($path), 404);
        return response()->download($path, $backup->filename);
    }
}
