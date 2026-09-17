<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\IdentityAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InstituteUserRegisterController extends Controller
{
    public function showRegisterForm(): View|RedirectResponse
    {
        if (Auth::guard('institute_user')->check()) {
            return redirect('/');
        }

        $this->logBlockedAttempt();

        return view('auth.register-disabled');
    }

    public function register(): RedirectResponse
    {
        $this->logBlockedAttempt();

        return redirect()
            ->route('institute.register')
            ->withErrors(['error' => 'Staff self-registration is disabled. Please contact your organization administrator.']);
    }

    protected function logBlockedAttempt(): void
    {
        try {
            IdentityAuditLog::create([
                'event' => 'staff_registration_blocked',
                'identifier_type' => 'email',
                'ip_address' => request()->ip(),
                'meta' => [
                    'email' => request()->input('email'),
                    'institute_id' => request()->input('institute_id'),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                ],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
