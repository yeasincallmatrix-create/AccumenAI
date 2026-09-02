<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => mawa_lang('auth.verification_link_sent')]);
            }
            return redirect()->intended('/');
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('verification_notification_failed', [
                'user_id' => $user->getKey(),
                'error' => substr($e->getMessage(), 0, 300),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => mawa_lang('auth.verification_link_sent')]);
        }

        return back()->with('status', mawa_lang('auth.verification_link_sent'));
    }
}
