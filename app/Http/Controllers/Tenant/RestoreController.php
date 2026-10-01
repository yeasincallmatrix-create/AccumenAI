<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
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
                'tenant_id'       => $tenantId,
                'backup_id'       => $backupId,
                'user_id'         => auth()->id(),
                'mode'            => 'merge',
                'status'          => 'pending',
                'progress_stage'  => 'queued',
                'progress_message' => 'Queued...',
            ]);

            \App\Jobs\RestoreJob::dispatch(
                $log->id,
                $backupId,
                $tenantId,
                auth()->id(),
                $user->email
            );

            return response()->json([
                'success'  => true,
                'message'  => 'Restore queued. You will be emailed when it completes.',
                'log_id'   => $log->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function progress(int $logId)
    {
        $log = \App\Models\RestoreLog::where('tenant_id', auth()->user()->institute_id)
            ->findOrFail($logId);

        return response()->json([
            'id'                => $log->id,
            'status'            => $log->status,
            'progress_percent'  => (int) $log->progress_percent,
            'progress_stage'    => $log->progress_stage,
            'progress_message'  => $log->progress_message,
            'total_chunks'      => (int) $log->total_chunks,
            'downloaded_chunks' => (int) $log->downloaded_chunks,
            'error_message'     => $log->error_message,
        ]);
    }
}
