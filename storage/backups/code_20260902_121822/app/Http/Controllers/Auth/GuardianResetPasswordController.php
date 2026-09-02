<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\PasswordService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class GuardianResetPasswordController extends Controller
{
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.guardian-reset-password', [
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

        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        $status = Password::broker('guardians')->reset($credentials, function ($user, string $password) {
            app(PasswordService::class)->setForUser($user, $password);
            $user->forceFill([
                'failed_login_count' => 0,
                'locked_until' => null,
            ])->save();

            $user->setRememberToken(Str::random(60));
            $user->save();

            app(PasswordService::class)->recordSecurityEvent($user, 'password_reset_completed');
            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('guardian.login')->with('status', mawa_lang('auth.password_reset_done'));
        }

        logger()->info('password security event', [
            'action' => 'password_reset_failed',
            'status' => $status,
        ]);

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => mawa_lang('auth.password_reset_failed')]);
    }
}
