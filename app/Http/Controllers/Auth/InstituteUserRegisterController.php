<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Auth\PasswordService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InstituteUserRegisterController extends Controller
{
    /**
     * New self-registered staff are created with this role until an admin approves them.
     */
    protected string $defaultRoleSlug = 'institute-admin';

    public function showRegisterForm(): View|RedirectResponse
    {
        if (Auth::guard('institute_user')->check()) {
            return redirect('/');
        }

        $institutes = Institute::query()
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('auth.register', ['institutes' => $institutes]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'institute_id' => ['required', 'integer', 'exists:institutes,id'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        $normalizedEmail = \App\Support\EmailNormalizer::normalize($data['email']);
        $normalizedPhone = \App\Support\PhoneNormalizer::toE164($data['phone'], 'Bangladesh');
        if ($normalizedPhone === null) {
            return back()->withErrors(['phone' => 'Invalid phone number.'])->withInput();
        }
        // Cross-table duplicate check for email/phone to prevent ambiguous broker routing
        if (\App\Models\User::where('email', $normalizedEmail)->exists() || \App\Models\PlatformAdmin::where('email', $normalizedEmail)->exists() || InstituteUser::where('email', $normalizedEmail)->exists()) {
            return back()->withErrors(['email' => 'Email already taken.'])->withInput();
        }
        if (InstituteUser::where('phone', $normalizedPhone)->exists() || \App\Models\User::where('phone', $normalizedPhone)->exists()) {
            return back()->withErrors(['phone' => 'Phone already taken.'])->withInput();
        }
        // Domain policy still applies but config-driven; ownership still requires verification
        if (!\App\Services\Identity\EmailDomainPolicy::isAllowed($normalizedEmail)) {
            return back()->withErrors(['email' => 'Email domain is not allowed.'])->withInput();
        }

        $role = Role::query()->where('slug', $this->defaultRoleSlug)->value('id');

        InstituteUser::create([
            'institute_id' => $data['institute_id'],
            'role_id' => $role,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $normalizedEmail,
            'phone' => $normalizedPhone,
            'preferred_language' => 'en',
            'password_hash' => app(PasswordService::class)->hash($data['password']),
            'status' => 'inactive',
        ]);

        return redirect()
            ->route('institute.login')
            ->with('status', mawa_lang('auth.registration_pending'));
    }
}
