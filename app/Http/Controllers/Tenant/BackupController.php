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

        // F8: Drive state resolved server-side — no JS fetch for button visibility.
        $driveConn = TenantDriveConnection::where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->first();

        return view('tenant.backup.index', compact('backups', 'driveConn'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $tenantId = $user->institute_id;

        // Pre-flight 1: Drive must be connected BEFORE queueing (async runs
        // later — fail fast here with a user-facing message).
        $conn = TenantDriveConnection::where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->first();
        if (!$conn) {
            return $this->failResponse(
                $request,
                'Google Drive not connected. Please connect Drive first.',
                422
            );
        }

        // Phase 2D — pre-flight 2: already locked? → immediate 409 so the
        // user gets a clear answer instead of a job that dies later.
        if (app(\App\Services\Backup\TenantLockService::class)->isLocked($tenantId)) {
            return $this->failResponse(
                $request,
                'Another backup/restore is running. Please wait.',
                409
            );
        }

        try {
            $backup = $this->service->createPendingBackup($tenantId, auth()->id());

            // Phase 2C: queue the work; UI polls progress endpoint.
            \App\Jobs\BackupJob::dispatch(
                $backup->id,
                $tenantId,
                auth()->id(),
                $user->email
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success'    => true,
                    'backup_id'  => $backup->id,
                    'message'    => 'Backup queued. You will be emailed when it completes.',
                ]);
            }

            return back()->with('success', 'Backup queued. You will be emailed when it completes.');
        } catch (\Throwable $e) {
            return $this->failResponse($request, $e->getMessage(), 422);
        }
    }

    /**
     * Phase 2D: single failure responder. 'error' + 'message' keys both
     * present — 'error' per spec contract, 'message' kept for the existing
     * progress-modal JS.
     */
    private function failResponse(Request $request, string $error, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error'   => $error,
                'message' => $error,
            ], $status);
        }

        return back()->withErrors(['error' => $error]);
    }

    public function progress(int $id)
    {
        $backup = Backup::where('tenant_id', auth()->user()->institute_id)->findOrFail($id);

        return response()->json([
            'id'                => $backup->id,
            'status'            => $backup->status,
            'progress_percent'  => (int) $backup->progress_percent,
            'progress_stage'    => $backup->progress_stage,
            'progress_message'  => $backup->progress_message,
            'total_chunks'      => (int) $backup->total_chunks,
            'uploaded_chunks'   => (int) $backup->uploaded_chunks,
            'error_message'     => $backup->error_message,
        ]);
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
