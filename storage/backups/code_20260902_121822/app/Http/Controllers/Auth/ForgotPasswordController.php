<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\IdentityAuditService;
use App\Support\EmailNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
        ]);

        $normalized = EmailNormalizer::normalize($request->input('email'));
        // Generic audit without revealing existence
        IdentityAuditService::log(null, 'password_reset_requested', 'email', ['ip' => $request->ip()]);

        // Probe all portals with normalized email. Only the broker that actually owns the email will issue a link;
        // response is identical either way so we never reveal which portal (if any) the address belongs to.
        $sent = false;
        foreach (['users', 'institute_users', 'platform_admins'] as $broker) {
            $status = Password::broker($broker)->sendResetLink(['email' => $normalized]);
            if ($status === Password::RESET_LINK_SENT) {
                $sent = true;
            }
        }

        // Always generic response - never reveal existence
        // Password::RESET_LINK_SENT and INVALID_USER both return same status message
        return back()->with('status', mawa_lang('auth.reset_link_sent'));
    }
}
