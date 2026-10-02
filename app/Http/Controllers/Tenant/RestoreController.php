<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\RestorePreview;
use App\Models\RestoreRollback;
use App\Services\Backup\RestorePreviewService;
use App\Services\Backup\RestoreRollbackService;
use App\Services\Backup\RestoreService;
use Illuminate\Http\Request;

class RestoreController extends Controller
{
    public function __construct(private RestoreService $service) {}

    public function requestOtp(Request $request, int $backupId)
    {
        try {
            $this->service->requestOtp(
                auth()->user()->institute_id,
                $backupId,
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to your email. Valid for 10 minutes.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function verify(Request $request, int $backupId)
    {
        $request->validate(['otp' => 'required|string|size:6']);

        $user = auth()->user();
        $tenantId = $user->institute_id;

        // Phase 3: optional preview carries the chosen mode (merge|smart).
        $preview = null;
        if ($request->filled('preview_id')) {
            $preview = RestorePreview::where('tenant_id', $tenantId)
                ->findOrFail((int) $request->input('preview_id'));

            if ($preview->isExpired()) {
                return response()->json(
                    ['success' => false, 'message' => 'Preview expired — run preview again.'],
                    410
                );
            }

            if ($preview->mode === RestoreService::MODE_SMART && !$this->isOwner($user)) {
                return response()->json(
                    ['success' => false, 'message' => 'Smart restore requires owner permission.'],
                    403
                );
            }

            if ((int) $preview->backup_id !== (int) $backupId) {
                return response()->json(
                    ['success' => false, 'message' => 'Preview does not match this backup.'],
                    422
                );
            }
        }

        $mode = $preview?->mode ?? RestoreService::MODE_MERGE;

        // Phase 2D — lock pre-flight BEFORE the OTP token is consumed, so a
        // concurrent-operation rejection never burns the user's code.
        if (app(\App\Services\Backup\TenantLockService::class)->isLocked($tenantId)) {
            $msg = 'Another backup/restore is running. Please wait.';
            return response()->json([
                'success' => false,
                'error'   => $msg,
                'message' => $msg,
            ], 409);
        }

        try {
            // OTP gate runs SYNC (token consumed exactly once here).
            $this->service->verifyOtpToken($tenantId, $backupId, auth()->id(), $request->otp);

            // Create the pending log row up-front so progress can poll it.
            $log = \App\Models\RestoreLog::create([
                'tenant_id'        => $tenantId,
                'backup_id'        => $backupId,
                'user_id'          => auth()->id(),
                'mode'             => $mode,
                'status'           => 'pending',
                'progress_stage'   => 'queued',
                'progress_message' => 'Queued...',
            ]);

            \App\Jobs\RestoreJob::dispatch(
                $log->id,
                $backupId,
                $tenantId,
                auth()->id(),
                $user->email,
                $mode
            );

            if ($preview && !$preview->isConfirmed()) {
                $preview->update(['confirmed_at' => now()]);
            }

            return response()->json([
                'success'  => true,
                'message'  => 'Restore queued. You will be emailed when it completes.',
                'log_id'   => $log->id,
                'mode'     => $mode,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Phase 3 — dry-run preview of what a restore would change.
     */
    public function preview(Request $request, int $backupId)
    {
        $user = auth()->user();
        $tenantId = $user->institute_id;

        $mode = $request->input('mode', RestoreService::MODE_MERGE);
        if (!in_array($mode, [RestoreService::MODE_MERGE, RestoreService::MODE_SMART], true)) {
            return response()->json(['success' => false, 'error' => 'Invalid mode'], 422);
        }

        if ($mode === RestoreService::MODE_SMART && !$this->isOwner($user)) {
            return response()->json(
                ['success' => false, 'error' => 'Smart mode requires owner permission'],
                403
            );
        }

        if (!\App\Models\Backup::where('tenant_id', $tenantId)->where('id', $backupId)->exists()) {
            return response()->json(['success' => false, 'error' => 'Backup not found'], 404);
        }

        // Legacy (pre-manifest) backups cannot be diffed — caller falls back
        // to the plain OTP restore path.
        if (!\App\Models\BackupManifest::where('backup_id', $backupId)->exists()) {
            return response()->json([
                'success' => false,
                'error'   => 'no_manifest',
                'message' => 'This backup has no manifest — preview unavailable.',
            ], 409);
        }

        try {
            $preview = app(RestorePreviewService::class)
                ->compute($tenantId, $backupId, $user->id, $mode);

            return response()->json([
                'success'           => true,
                'preview_id'        => $preview->id,
                'mode'              => $preview->mode,
                'total_insert'      => $preview->total_insert,
                'total_update'      => $preview->total_update,
                'total_soft_delete' => $preview->total_soft_delete,
                'total_kept'        => $preview->total_kept,
                'diff'              => $preview->diff,
                'expires_at'        => $preview->expires_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Restore preview failed', [
                'backup_id' => $backupId,
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Phase 3 — intent confirmation (type RESTORE). Kicks off the OTP step;
     * the restore itself is only dispatched after the OTP gate in verify().
     */
    public function confirm(Request $request, int $previewId)
    {
        $user = auth()->user();

        $preview = RestorePreview::where('tenant_id', $user->institute_id)
            ->findOrFail($previewId);

        if ($preview->isExpired()) {
            return response()->json(['success' => false, 'error' => 'Preview expired'], 410);
        }

        if ($preview->isConfirmed()) {
            return response()->json(['success' => false, 'error' => 'Already confirmed'], 409);
        }

        if ($preview->mode === RestoreService::MODE_SMART && !$this->isOwner($user)) {
            return response()->json(
                ['success' => false, 'error' => 'Smart mode requires owner permission'],
                403
            );
        }

        if ((string) $request->input('confirm_phrase') !== 'RESTORE') {
            return response()->json(['success' => false, 'error' => 'Type RESTORE to confirm'], 422);
        }

        try {
            $this->service->requestOtp($user->institute_id, $preview->backup_id, $user->id);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        $preview->update(['confirmed_at' => now()]);

        return response()->json([
            'success'    => true,
            'preview_id' => $preview->id,
            'mode'       => $preview->mode,
            'backup_id'  => $preview->backup_id,
            'needs_otp'  => true,
            'message'    => 'OTP sent to your email. Enter it to start the restore.',
        ]);
    }

    /**
     * Phase 3 — undo a smart restore using the rollback token.
     */
    public function rollback(Request $request, string $token)
    {
        $user = auth()->user();
        $rollback = RestoreRollback::where('rollback_token', $token)->first();

        if (!$rollback) {
            return $this->rollbackResponse($request, ['error' => 'Rollback not found'], 404);
        }

        if ((int) $user->institute_id !== (int) $rollback->tenant_id) {
            abort(403);
        }

        if (!$this->isOwner($user)) {
            return $this->rollbackResponse(
                $request,
                ['error' => 'Rollback requires owner permission'],
                403
            );
        }

        if (!$rollback->isUsable()) {
            return $this->rollbackResponse(
                $request,
                ['error' => 'Rollback expired or already used'],
                410
            );
        }

        try {
            $result = app(RestoreRollbackService::class)->rollback($rollback);

            \Log::info('Smart restore rolled back', [
                'tenant_id'      => $rollback->tenant_id,
                'restore_log_id' => $rollback->restore_log_id,
                'restored'       => $result['restored'],
            ]);

            return $this->rollbackResponse($request, [
                'success'  => true,
                'message'  => "Restored {$result['restored']} rows",
                'restored' => $result['restored'],
                'failed'   => $result['failed'],
            ]);
        } catch (\Throwable $e) {
            return $this->rollbackResponse($request, ['error' => $e->getMessage()], 422);
        }
    }

    public function progress(int $logId)
    {
        $log = \App\Models\RestoreLog::where('tenant_id', auth()->user()->institute_id)
            ->findOrFail($logId);

        return response()->json([
            'id'                => $log->id,
            'status'            => $log->status,
            'mode'              => $log->mode,
            'progress_percent'  => (int) $log->progress_percent,
            'progress_stage'    => $log->progress_stage,
            'progress_message'  => $log->progress_message,
            'total_chunks'      => (int) $log->total_chunks,
            'downloaded_chunks' => (int) $log->downloaded_chunks,
            'error_message'     => $log->error_message,
            'rollback_token'    => $log->rollback_token,
        ]);
    }

    /**
     * Gate E — owner check by role slug (never by role id).
     */
    private function isOwner($user): bool
    {
        return $user !== null && method_exists($user, 'hasRole')
            && $user->hasRole('institute-owner');
    }

    private function rollbackResponse(Request $request, array $payload, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json($payload, $status);
        }

        if (($payload['success'] ?? false)) {
            return back()->with('success', $payload['message']);
        }

        return back()->withErrors(['error' => $payload['error'] ?? 'Rollback failed']);
    }
}
