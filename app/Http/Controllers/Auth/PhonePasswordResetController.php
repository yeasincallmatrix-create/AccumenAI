<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\PhonePasswordRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PhonePasswordResetController extends Controller
{
    public function __construct(protected PhonePasswordRecoveryService $service) {}

    public function showRequestForm(): View
    {
        return view('auth.forgot-password-phone');
    }

    public function requestOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $this->service->request($request->input('phone'), null, $request->ip());

        // Always generic response - never reveal existence
        return back()->with('status', __('If an account exists with that phone, a reset code has been sent.'));
    }

    public function showVerifyForm(Request $request): View
    {
        return view('auth.verify-phone-otp', ['phone' => $request->query('phone', '')]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'otp' => ['required', 'string', 'size:6'],
        ]);

        $this->service->verify($request->input('phone'), $request->input('otp'));

        return redirect()->route('password.phone.reset.form', ['phone' => $request->input('phone')])->with('status', __('Code verified. You may now reset your password.'));
    }

    public function showResetForm(Request $request): View
    {
        return view('auth.reset-password-phone', ['phone' => $request->query('phone', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        // Also require otp has been verified previously; service checks cache/DB verified state
        // If client still sends otp, verify again; else require prior verification
        if ($request->filled('otp')) {
            try {
                $this->service->verify($request->input('phone'), $request->input('otp'));
            } catch (\Illuminate\Validation\ValidationException $e) {
                // If already verified, ignore
                if (!str_contains($e->getMessage(), 'already')) throw $e;
            }
        }

        $this->service->reset($request->input('phone'), $request->input('password'));

        return redirect()->route('login')->with('status', __('Password has been reset. Please login.'));
    }
}
