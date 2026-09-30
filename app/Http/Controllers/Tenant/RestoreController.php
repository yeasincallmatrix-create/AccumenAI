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

        try {
            $log = $this->service->verifyAndRestore(
                auth()->user()->institute_id,
                $backupId,
                auth()->id(),
                $request->otp
            );

            return response()->json([
                'success' => true,
                'message' => 'Restore completed successfully.',
                'affected' => $log->records_affected,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
