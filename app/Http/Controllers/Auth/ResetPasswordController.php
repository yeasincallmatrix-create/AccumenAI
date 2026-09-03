<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Services\Identity\IdentityAuditService;
use App\Support\EmailNormalizer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Auth\PasswordService;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        $normalizedEmail = EmailNormalizer::normalize($request->input('email'));
        $credentials = [
            'email' => $normalizedEmail,
            'password' => $request->input('password'),
            'password_confirmation' => $request->input('password_confirmation'),
            'token' => $request->input('token'),
        ];

        // Tenant-safe reset — super admin excluded per policy (no password recovery for super admin)
        $brokers = ['users', 'institute_users'];
        $status = null;
        foreach ($brokers as $broker) {
            $status = Password::broker($broker)->reset($credentials, function ($user, string $password) {
                app(PasswordService::class)->setForUser($user, $password);
                // Clear per-user lockout if present on the model (platform_admin now has columns)
                if (property_exists($user, 'failed_login_count') || isset($user->failed_login_count)) {
                    try { $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save(); } catch (\Throwable $e) { report($e); }
                }
                $user->setRememberToken(Str::random(60));
                $user->save();
                // Audit success without logging password/token
                IdentityAuditService::log($user->getAuthIdentifier(), 'password_reset_completed', 'email', []);
                app(PasswordService::class)->recordSecurityEvent($user, 'password_reset_completed');
                event(new PasswordReset($user));
            });
            if ($status === Password::PASSWORD_RESET) {
                break;
            }
        }

        if ($status === Password::PASSWORD_RESET) {
            // Session revocation already handled inside PasswordService::setForUser
            return redirect()->route('institute.login')->with('status', mawa_lang('auth.password_reset_done'));
        }

        // Log failed reset without revealing account existence (no hash/token logged)
        // Token expiration / invalid / reused all map to INVALID_TOKEN; we audit generically
        IdentityAuditService::log(null, 'password_reset_failed', 'email', ['status' => $status]);
        logger()->info('password security event', [
            'action' => 'password_reset_failed',
            'status' => $status,
        ]);

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => mawa_lang('auth.password_reset_failed')]);
    }

    protected function brokerForEmail(string $email): PasswordBroker
    {
        $normalized = EmailNormalizer::normalize($email);
        if (User::query()->where('email', $normalized)->exists()) {
            return Password::broker('users');
        }

        return Password::broker('institute_users');
    }
}
