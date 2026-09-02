<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstituteOnboardingController;
use App\Services\UserAccountService;
use App\Support\IndustryRules;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Owner Account registration.
 *
 * Two-step public flow before the account exists:
 *   1. GET /register           — pick country → industry → sub-industry.
 *   2. GET /register/form      — personal details (selection shown locked).
 *   3. POST /register          — creates the owner account and lands the user
 *      directly on /workspace/create since the selection is already in the
 *      onboarding session.
 *
 * Creates a global AccumenAI account with account_type = owner via
 * UserAccountService::registerOwner(). The onboarding session is populated on
 * step 1 so the post-login workspace creation has its country/industry/scoping
 * ready and the picker/onboarding detour is skipped.
 */
class OwnerRegisterController extends Controller
{
    public function showSelection(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register-select', [
            'industries' => IndustryRules::industries(null),
            'countries' => config('countries', []),
            'rules' => Arr::except(config('industry_rules', []), ['global', 'capabilities']),
            'selection' => (array) session(InstituteOnboardingController::SESSION_KEY, []),
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $validated = InstituteOnboardingController::validatedSelection($request->all());

        session([InstituteOnboardingController::SESSION_KEY => $validated]);

        return redirect()->route('owner.register.form');
    }

    public function showForm(Request $request): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        $selection = InstituteOnboardingController::selection();
        if ($selection === null) {
            return redirect()->route('owner.register');
        }

        return view('auth.register-owner', [
            'selection' => $selection,
            'countryLabel' => config('countries.'.$selection['country'], $selection['country']),
            'industryLabel' => IndustryRules::label($selection['country'], $selection['industry']) ?? $selection['industry'],
            'subIndustryLabel' => $selection['sub_industry'] !== null
                ? (IndustryRules::subIndustries($selection['country'], $selection['industry'])[$selection['sub_industry']] ?? $selection['sub_industry'])
                : null,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $selection = InstituteOnboardingController::selection();
        if ($selection === null) {
            return redirect()
                ->route('owner.register')
                ->withErrors(['country' => 'Please choose your country and industry first.']);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        $normalizedEmail = \App\Support\EmailNormalizer::normalize($data['email']);
        $rawPhone = $data['phone'];
        $country = $selection['country'] ?? 'Bangladesh';
        $normalizedPhone = \App\Support\PhoneNormalizer::toE164($rawPhone, $country);
        if ($normalizedPhone === null) {
            return back()->withErrors(['phone' => 'Invalid phone number.'])->withInput();
        }
        if (! \App\Services\Identity\EmailDomainPolicy::isAllowed($normalizedEmail)) {
            return back()->withErrors(['email' => 'Email domain is not allowed.'])->withInput();
        }
        // Prevent duplicate account creation (enumeration-safe: same generic error but we return validation)
        // Cross-table check prevents ambiguous broker routing (E9.2)
        if (\App\Models\User::where('email', $normalizedEmail)->exists() || \App\Models\InstituteUser::where('email', $normalizedEmail)->exists() || \App\Models\PlatformAdmin::where('email', $normalizedEmail)->exists()) {
            return back()->withErrors(['email' => 'Email already taken.'])->withInput();
        }
        if (\App\Models\User::where('phone', $normalizedPhone)->exists() || \App\Models\InstituteUser::where('phone', $normalizedPhone)->exists()) {
            return back()->withErrors(['phone' => 'Phone already taken.'])->withInput();
        }

        $user = app(UserAccountService::class)->registerOwner([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $normalizedEmail,
            'phone' => $normalizedPhone,
            'preferred_language' => mawa_current_lang(),
            'password_hash' => app(\App\Services\Auth\PasswordService::class)->hash($data['password']),
            'status' => 'active',
        ]);

        // Dispatch verification email via existing MustVerifyEmail flow (queued).
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        // E18: Mandatory SMS OTP phone verification (user-friendly default).
        // Sent immediately after registration; verification does not block workspace creation
        // but ensures normal users verify via SMS without needing Authenticator App.
        try {
            app(\App\Services\Identity\PhoneOtpService::class)->send($user, $normalizedPhone, $country);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('verification.notice')
            ->with('status', mawa_lang('auth.owner_registered'));
    }
}
