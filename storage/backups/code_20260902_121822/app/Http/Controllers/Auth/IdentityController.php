<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\EmailChangeService;
use App\Services\Identity\IdentityAuditService;
use App\Services\Identity\PhoneChangeService;
use App\Services\Identity\PhoneOtpService;
use App\Support\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class IdentityController extends Controller
{
    public function __construct(
        protected EmailChangeService $emailChange,
        protected PhoneChangeService $phoneChange,
        protected PhoneOtpService $phoneOtp
    ) {}

    // Phone verification: send OTP for current phone
    public function sendPhoneVerification(Request $request)
    {
        $user = $request->user();
        if (!$user->phone) {
            throw ValidationException::withMessages(['phone' => ['No phone on file.']]);
        }
        $this->confirmPassword($request, $user);
        $this->phoneOtp->send($user, $user->phone);

        return back()->with('status', 'Verification code sent.');
    }

    public function verifyPhone(Request $request)
    {
        $request->validate(['otp' => ['required','string','size:6']]);
        $user = $request->user();
        if (!$user->phone) {
            throw ValidationException::withMessages(['phone' => ['No phone on file.']]);
        }
        $this->phoneOtp->verify($user, $user->phone, $request->input('otp'));
        $user->forceFill(['phone_verified_at' => now()])->save();
        IdentityAuditService::log($user->id, 'phone_verified', 'phone', []);
        return back()->with('status', 'Phone verified.');
    }

    // Email change
    public function requestEmailChange(Request $request)
    {
        $request->validate(['email' => ['required','string','email','max:150'], 'password' => ['required','string']]);
        $user = $request->user();
        $this->confirmPassword($request, $user);
        $this->emailChange->requestChange($user, $request->input('email'));
        return back()->with('status', 'Verification email sent to new address.');
    }

    public function verifyEmailChange(Request $request)
    {
        $request->validate(['token' => ['required','string'], 'email' => ['required','string','email']]);
        $user = $request->user();
        // token via query or body
        $this->emailChange->verify($user, $request->input('token'), $request->input('email'));
        return back()->with('status', 'Email changed successfully.');
    }

    // For email verification link clicking (GET)
    public function verifyEmailChangeLink(Request $request)
    {
        $request->validate(['token' => ['required','string'], 'email' => ['required','string','email']]);
        $user = $request->user();
        if (!$user) {
            // If not authenticated, require login - but for test we handle authenticated only
            return redirect()->route('login');
        }
        $this->emailChange->verify($user, $request->query('token') ?? $request->input('token'), $request->query('email') ?? $request->input('email'));
        return redirect()->route('account.security')->with('status', 'Email changed successfully.');
    }

    // Phone change
    public function requestPhoneChange(Request $request)
    {
        $request->validate(['phone' => ['required','string','max:30'], 'password' => ['required','string']]);
        $user = $request->user();
        $this->confirmPassword($request, $user);
        $this->phoneChange->requestChange($user, $request->input('phone'));
        return back()->with('status', 'Verification code sent to new phone.');
    }

    public function verifyPhoneChange(Request $request)
    {
        $request->validate(['otp' => ['required','string','size:6']]);
        $user = $request->user();
        $this->phoneChange->verifyChange($user, $request->input('otp'));
        return back()->with('status', 'Phone changed successfully.');
    }

    // Email removal
    public function removeEmail(Request $request)
    {
        $request->validate(['password' => ['required','string']]);
        $user = $request->user();
        $this->confirmPassword($request, $user);

        // Ensure another recovery channel remains (phone verified)
        if (!$user->phone || !$user->phone_verified_at) {
            throw ValidationException::withMessages(['email' => ['Cannot remove email without verified phone as recovery.']]);
        }
        // Also ensure 2FA? For now password is considered recent auth
        if ($user->hasEnabledTwoFactorAuthentication() && !$user->two_factor_confirmed_at) {
            // if 2FA enabled but not confirmed, still allow password only? We'll require password only per spec
        }

        $old = $user->email;
        $user->forceFill([
            'email' => null,
            'email_verified_at' => null,
            'pending_email' => null,
            'pending_email_token_hash' => null,
            'pending_email_expires_at' => null,
        ])->save();

        IdentityAuditService::log($user->id, 'email_removed', 'email', ['old' => $this->maskEmail($old)]);
        return back()->with('status', 'Email removed.');
    }

    // Phone removal
    public function removePhone(Request $request)
    {
        $request->validate(['password' => ['required','string']]);
        $user = $request->user();
        $this->confirmPassword($request, $user);

        // Ensure verified email exists
        if (!$user->email || !$user->email_verified_at) {
            throw ValidationException::withMessages(['phone' => ['Cannot remove phone without verified email as recovery.']]);
        }

        $old = $user->phone;
        $user->forceFill([
            'phone' => null,
            'phone_verified_at' => null,
            'pending_phone' => null,
        ])->save();

        IdentityAuditService::log($user->id, 'phone_removed', 'phone', ['old' => $old ? $this->maskPhone($old) : null]);
        return back()->with('status', 'Phone removed.');
    }

    protected function confirmPassword(Request $request, $user): void
    {
        if (! \App\Support\PasswordHash::safeCheck($request->input('password'), $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => ['Incorrect password.']]);
        }
        // If 2FA enabled, require recent 2FA? For now password suffices; could extend to require TOTP code if present
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts)!==2) return '***';
        return substr($parts[0],0,1).str_repeat('*', max(1, strlen($parts[0])-2)).substr($parts[0],-1).'@'.$parts[1];
    }
    protected function maskPhone(string $phone): string
    {
        $len=strlen($phone);
        if($len<=4) return str_repeat('*',$len);
        return substr($phone,0,3).str_repeat('*',$len-6).substr($phone,-3);
    }
}
