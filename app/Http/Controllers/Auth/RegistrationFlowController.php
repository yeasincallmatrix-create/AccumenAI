<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstituteOnboardingController;
use App\Models\Country;
use App\Models\Institute;
use App\Models\PendingRegistration;
use App\Models\Role;
use App\Models\User;
use App\Services\Identity\PendingRegistrationOtpService;
use App\Services\MembershipService;
use App\Support\IndustryRules;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RegistrationFlowController extends Controller
{
    public const SESSION_KEY = 'registration_flow';
    public const PENDING_ID = 'pending_registration_id';

    // ---- Step 1 : Account Credentials ----
    public function showAccount(Request $request): View|RedirectResponse
    {
        if ($request->user('web')) {
            $user = $request->user('web');
            if (self::isOnboardingIncomplete($user)) {
                $resume = self::resumeRouteForUser($user) ?? 'register.organization';
                // Restore session for middleware continuity
                $pending = self::findPendingForUser($user);
                if ($pending) {
                    session([self::PENDING_ID => $pending->id, self::SESSION_KEY => ['email' => $pending->email, 'verified' => true, 'step' => 2]]);
                }
                return redirect()->route($resume);
            }
            return redirect()->route('dashboard');
        }
        // Guest with verified pending session should skip to next step
        $pending = $this->resolvePending($request);
        if ($pending && $pending->isVerified()) {
            if (empty($pending->organization_data)) return redirect()->route('register.organization');
            if (empty($pending->address_data)) return redirect()->route('register.address');
        }
        return view('auth.register-account', [
            'email' => session(self::SESSION_KEY . '.email'),
        ]);
    }

    public function storeAccount(Request $request): RedirectResponse
    {
        // Layered abuse protection: per-IP and per-normalized-email
        $ipKey = 'register_account_ip:' . $request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($ipKey, 10)) {
            return back()->withErrors(['email' => 'Too many attempts. Please try again later.'])->withInput();
        }
        // Pre-validate email format for rate-key before full validation to avoid bypass via whitespace/casing
        $rawEmailForKey = strtolower(trim((string) $request->input('email', '')));
        $emailKey = 'register_account_email:' . md5($rawEmailForKey);
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($emailKey, 5)) {
            \Illuminate\Support\Facades\RateLimiter::hit($ipKey, 3600);
            return back()->withErrors(['email' => 'Too many attempts. Please try again later.'])->withInput();
        }
        \Illuminate\Support\Facades\RateLimiter::hit($ipKey, 3600);
        \Illuminate\Support\Facades\RateLimiter::hit($emailKey, 3600);

        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        $normalizedEmail = \App\Support\EmailNormalizer::normalize($data['email']);
        if (! \App\Services\Identity\EmailDomainPolicy::isAllowed($normalizedEmail)) {
            return back()->withErrors(['email' => 'Email domain is not allowed.'])->withInput();
        }
        // Cross-table duplicate + pending check — allow resumable onboarding (OTP verified but org not done)
        $hasUser = \App\Models\User::where('email', $normalizedEmail)->exists();
        $hasPendingVerified = PendingRegistration::where('email', $normalizedEmail)->whereNotNull('verified_at')->exists();
        if ($hasUser && $hasPendingVerified) {
            $tmpUser = \App\Models\User::where('email', $normalizedEmail)->first();
            if ($tmpUser && self::isOnboardingIncomplete($tmpUser)) {
                $resume = self::resumeRouteForUser($tmpUser) ?? 'register.organization';
                $pendingResume = self::findPendingForUser($tmpUser);
                if ($pendingResume) {
                    session([self::PENDING_ID => $pendingResume->id, self::SESSION_KEY => ['email' => $pendingResume->email, 'verified' => true, 'step' => 2]]);
                    return redirect()->route($resume);
                }
            }
        }
        if (\App\Models\User::where('email', $normalizedEmail)->exists()
            || \App\Models\InstituteUser::where('email', $normalizedEmail)->exists()
            || \App\Models\PlatformAdmin::where('email', $normalizedEmail)->exists()
            || PendingRegistration::where('email', $normalizedEmail)->whereNull('verified_at')->where('expires_at', '>', now())->exists()
        ) {
            // If pending exists but expired, allow reuse by deleting expired
            $existingPending = PendingRegistration::where('email', $normalizedEmail)->first();
            if ($existingPending && $existingPending->expires_at && $existingPending->expires_at->isPast()) {
                $existingPending->delete();
            } else if (\App\Models\User::where('email', $normalizedEmail)->exists() || \App\Models\InstituteUser::where('email', $normalizedEmail)->exists() || \App\Models\PlatformAdmin::where('email', $normalizedEmail)->exists()) {
                // Verified pending with incomplete onboarding already handled above — now it's true duplicate
                $maybePending = PendingRegistration::where('email', $normalizedEmail)->whereNotNull('verified_at')->first();
                if ($maybePending && ! $maybePending->isAbandonedExpired() && self::findPendingForUser(\App\Models\User::where('email', $normalizedEmail)->first() ?? new \App\Models\User(['email' => $normalizedEmail]))) {
                    // still resumable, don't block
                } else {
                    return back()->withErrors(['email' => 'Email already taken.'])->withInput();
                }
            }
        }

        // Check duplicate pending still valid
        $existing = PendingRegistration::where('email', $normalizedEmail)->first();
        if ($existing && !$existing->isVerified() && $existing->otp_expires_at && !$existing->isGraceExpired()) {
            // Reuse existing pending if not verified - resend OTP instead of duplicate error
            // Update password hash to latest via canonical PasswordService
            $existing->update([
                'password_hash' => app(\App\Services\Auth\PasswordService::class)->hash($data['password']),
                'expires_at' => now()->addHours(24),
            ]);
            $pending = $existing;
        } else {
            if ($existing) { $existing->delete(); }
            $pending = PendingRegistration::create([
                'email' => $normalizedEmail,
                'password_hash' => app(\App\Services\Auth\PasswordService::class)->hash($data['password']),
                'expires_at' => now()->addHours(24),
                'attempts' => 0,
                'resend_count' => 0,
            ]);
        }

        session([
            self::PENDING_ID => $pending->id,
            self::SESSION_KEY => [
                'email' => $normalizedEmail,
                'verified' => false,
                'step' => 1,
            ]
        ]);
        // Regenerate session ID to prevent fixation
        $request->session()->regenerate();

        try {
            app(PendingRegistrationOtpService::class)->send($pending);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Throttled - still redirect to OTP page with error
            throw $e;
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('register.otp.form');
    }

    // ---- Step 2 : OTP Verification ----
    public function showOtp(Request $request): View|RedirectResponse
    {
        $pending = $this->resolvePending($request);
        if (!$pending) {
            return redirect()->route('register.account')->withErrors(['email' => 'Please start registration from the beginning.']);
        }
        if ($pending->isVerified()) {
            session([self::SESSION_KEY . '.verified' => true, self::SESSION_KEY . '.step' => 2]);
            return redirect()->route('register.organization');
        }
        $cooldown = $this->remainingCooldown($pending);
        return view('auth.register-otp', [
            'email' => $pending->email,
            'maskedEmail' => $this->maskEmail($pending->email),
            'expiresAt' => $pending->otp_expires_at,
            'cooldown' => $cooldown,
            'attempts' => $pending->attempts,
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $pending = $this->resolvePending($request);
        if (!$pending) {
            return redirect()->route('register.account');
        }
        $data = $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{4,8}$/'],
        ]);

        // Rate limit OTP attempts per pending
        $throttleKey = 'pending_otp_verify:' . $pending->id . ':' . $request->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 10)) {
            return back()->withErrors(['otp' => 'Too many verification attempts. Please try again later.']);
        }
        \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60);

        try {
            app(PendingRegistrationOtpService::class)->verify($pending, $data['otp']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }

        // Requirement: OTP verified = successful registration (User created) but no dashboard until steps complete.
        $this->ensureUserFromPending($pending->fresh());

        $request->session()->regenerate();
        session([self::SESSION_KEY . '.verified' => true, self::SESSION_KEY . '.step' => 2]);
        // Re-store pending id after regenerate to keep resumable via session + DB (email)
        session([self::PENDING_ID => $pending->id]);
        // Clear rate limiter on success
        \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

        return redirect()->route('register.organization')->with('status', 'Verification code verified successfully. Please continue.');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $pending = $this->resolvePending($request);
        if (!$pending) {
            return redirect()->route('register.account');
        }
        if ($pending->isVerified()) {
            return redirect()->route('register.organization');
        }
        try {
            app(PendingRegistrationOtpService::class)->send($pending);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['otp' => 'Failed to resend code. Please try again.']);
        }
        return back()->with('status', 'Verification code resent.');
    }

    // ---- Step 3 : Organization / Business Information ----
    public function showOrganization(Request $request): View|RedirectResponse
    {
        $pending = $this->resolvePending($request);
        if (!$pending || !$pending->isVerified()) {
            return redirect()->route('register.otp.form')->withErrors(['otp' => 'Please verify your email first.']);
        }
        return view('auth.register-organization', [
            'industries' => IndustryRules::industries(null),
            'countries' => config('countries', []),
            'rules' => Arr::except(config('industry_rules', []), ['global', 'capabilities']),
            'selection' => $pending->organization_data ?? [],
            'email' => $pending->email,
        ]);
    }

    public function storeOrganization(Request $request): RedirectResponse
    {
        $pending = $this->resolvePending($request);
        if (!$pending || !$pending->isVerified()) {
            return redirect()->route('register.otp.form');
        }
        $validated = InstituteOnboardingController::validatedSelection($request->all());
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:150'],
            // Also collect owner profile for final User creation (not in spec but needed)
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'phone' => ['required', 'string', 'max:30'],
        ]);
        $country = $validated['country'];
        $phoneNorm = \App\Support\PhoneNormalizer::toE164($data['phone'], $country);
        if ($phoneNorm === null) {
            return back()->withErrors(['phone' => 'Invalid phone number.'])->withInput();
        }
        if (\App\Models\User::where('phone', $phoneNorm)->exists() || \App\Models\InstituteUser::where('phone', $phoneNorm)->exists()) {
            return back()->withErrors(['phone' => 'Phone already taken.'])->withInput();
        }

        $pending->update([
            'organization_data' => array_merge($validated, [
                'organization_name' => $data['organization_name'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $phoneNorm,
            ])
        ]);
        session([self::SESSION_KEY . '.step' => 3]);

        return redirect()->route('register.address');
    }

    // ---- Step 4 : Local Address ----
    public function showAddress(Request $request): View|RedirectResponse
    {
        $pending = $this->resolvePending($request);
        if (!$pending || !$pending->isVerified()) {
            return redirect()->route('register.otp.form');
        }
        if (empty($pending->organization_data)) {
            return redirect()->route('register.organization');
        }
        $org = $pending->organization_data;
        $geoAddress = $this->geoAddress($org['country'] ?? 'Bangladesh');
        return view('auth.register-address', [
            'geoAddress' => $geoAddress,
            'selection' => $org,
            'addressData' => $pending->address_data ?? [],
            'email' => $pending->email,
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $pending = $this->resolvePending($request);
        if (!$pending || !$pending->isVerified()) {
            return redirect()->route('register.otp.form');
        }
        if (empty($pending->organization_data)) {
            return redirect()->route('register.organization');
        }
        $org = $pending->organization_data;
        $geoAddress = $this->geoAddress($org['country']);
        $data = $request->validate([
            'country_id' => ['nullable', 'integer'],
            'admin_1_id' => ['nullable', 'integer'],
            'admin_2_id' => ['nullable', 'integer'],
            'admin_3_id' => ['nullable', 'integer'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        if ($geoAddress !== null) {
            if (array_key_exists('country_id', $data) && $data['country_id'] !== null && $data['country_id'] !== '' && (int) $data['country_id'] !== (int) $geoAddress['country_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['country_id' => 'The address country must match the selected organization country.']);
            }
            $error = \App\Support\GeoHierarchy::validateHierarchy(
                (int) $geoAddress['country_id'],
                $data['admin_1_id'] ?? null,
                $data['admin_2_id'] ?? null,
                $data['admin_3_id'] ?? null
            );
            if ($error !== null) {
                throw \Illuminate\Validation\ValidationException::withMessages(['admin_1_id' => mawa_lang($error)]);
            }
        }

        $pending->update(['address_data' => $data]);
        session([self::SESSION_KEY . '.step' => 4]);

        // Create User + Institute now
        return $this->finalizeRegistration($pending, $request);
    }

    protected function finalizeRegistration(PendingRegistration $pending, Request $request): RedirectResponse
    {
        $org = $pending->organization_data;
        $addr = $pending->address_data ?? [];
        $geoAddress = $this->geoAddress($org['country']);

        $ownerRoleId = Role::query()->where('slug', 'institute-owner')->value('id');
        abort_unless($ownerRoleId !== null, 422, 'The institute-owner role is not configured.');

        $user = null;
        $institute = null;
        try {
            DB::transaction(function () use ($pending, $org, $addr, $geoAddress, $ownerRoleId, &$user, &$institute) {
            // Lock pending row to prevent concurrent finalization
            $lockedPending = PendingRegistration::whereKey($pending->id)->lockForUpdate()->first();
            if (!$lockedPending) throw new \Illuminate\Validation\ValidationException(validator([],[]), response()->redirectToRoute('register.account'));
            // User may already exist (created at OTP verification for resumable flow) — reuse it
            $existingUser = \App\Models\User::where('email', $lockedPending->email)->first();
            if ($existingUser) {
                $user = $existingUser;
                // Update profile fields from organization step if missing
                $user->forceFill([
                    'name' => trim($org['first_name'].' '.$org['last_name']),
                    'first_name' => $org['first_name'],
                    'last_name' => $org['last_name'],
                    'phone' => $org['phone'] ?? $user->phone,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            } else {
                $user = User::create([
                    'name' => trim($org['first_name'].' '.$org['last_name']),
                    'first_name' => $org['first_name'],
                    'last_name' => $org['last_name'],
                    'email' => $pending->email,
                    'phone' => $org['phone'],
                    'preferred_language' => mawa_current_lang(),
                    'password_hash' => $pending->password_hash,
                    'status' => 'active',
                    'account_type' => 'owner',
                    'email_verified_at' => now(),
                ]);
            }

            $institute = Institute::create([
                'name' => $org['organization_name'],
                'slug' => $this->uniqueSlug($org['organization_name']),
                'industry' => $org['industry'],
                'sub_industry' => $org['sub_industry'],
                'country' => $org['country'],
                'country_id' => $geoAddress['country_id'] ?? null,
                'admin_level_1_id' => $addr['admin_1_id'] ?? null,
                'admin_level_2_id' => $addr['admin_2_id'] ?? null,
                'admin_level_3_id' => $addr['admin_3_id'] ?? null,
                'postal_code' => $addr['zip_code'] ?? null,
                'address' => $addr['address'] ?? null,
                'status' => 'active',
            ]);

            app(MembershipService::class)->assign($user, $institute->id, $ownerRoleId, [
                'branch_id' => null,
                'status' => 'active',
            ]);

            // Default certificate approval to Admin Controlled (new institutes)
            \App\Models\InstituteSetting::withoutGlobalScope('institute')->updateOrCreate(
                ['institute_id' => $institute->id],
                ['certificate_approval_mode' => \App\Models\InstituteSetting::CERTIFICATE_APPROVAL_ADMIN]
            );

            // Clean pending (locked)
            $lockedPending->delete();
        });
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Duplicate email race — pending still exists for retry with new email or login
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }

        // Auto-assign learning structure etc (same as InstituteCreationController)
        try {
            $this->assignDefaultLearningStructure($institute);
        } catch (\Throwable $e) { Log::warning('RegistrationFlow: learning structure failed', ['institute_id' => $institute->id, 'error' => $e->getMessage()]); report($e); }
        try { app(\App\Services\AcademicSetupService::class)->ensureDefaults($institute); } catch (\Throwable $e) { Log::warning('RegistrationFlow: academic defaults failed', ['institute_id' => $institute->id, 'error' => $e->getMessage()]); report($e); }
        try { app(\App\Services\Demo\DemoDataService::class)->seed($institute, $user, ['force' => false]); } catch (\Throwable $e) { Log::warning('RegistrationFlow: demo seeding failed', ['institute_id' => $institute->id, 'error' => $e->getMessage()]); report($e); }

        // Log the new user in? Spec says do not automatically log in after Step1, but after full flow should land on setup/dashboard. We will log in now.
        \Illuminate\Support\Facades\Auth::guard('web')->login($user);
        $request->session()->regenerate();
        session()->forget([self::PENDING_ID, self::SESSION_KEY]);
        // Clear onboarding session if any
        \App\Http\Controllers\InstituteOnboardingController::clear();
        Workspace::set($institute->id);

        // Step 5 routing
        if (($org['industry'] ?? null) === 'education') {
            return redirect()->route('register.education.placeholder')->with('status', mawa_lang('workspace.created', ['name' => $institute->name]));
        }
        return redirect()->route('dashboard')->with('status', mawa_lang('workspace.created', ['name' => $institute->name]));
    }

    public function educationPlaceholder(Request $request): View
    {
        return view('auth.register-education-placeholder', [
            'institute' => $request->user('web')?->institutions()->latest()->first(),
        ]);
    }

    // ---- Helpers ----
    protected function resolvePending(Request $request): ?PendingRegistration
    {
        $id = session(self::PENDING_ID);
        $pending = $id ? PendingRegistration::find($id) : null;
        // Fallback: resume via authenticated user (after logout) — continue from same step
        if (!$pending && $request->user('web')) {
            $email = $request->user('web')->email;
            $pending = PendingRegistration::where('email', $email)
                ->whereNotNull('verified_at')
                ->latest('id')->first();
            if ($pending && !$pending->isAbandonedExpired() && !$pending->isGraceExpired()) {
                // Restore session for continuity
                session([self::PENDING_ID => $pending->id, self::SESSION_KEY => ['email' => $pending->email, 'verified' => true, 'step' => $this->detectStep($pending)]]);
                return $pending;
            }
            return null;
        }
        if (!$pending) {
            return null;
        }
        // Schedule is NOT security boundary — enforce synchronously
        if ($pending->isVerified() ? $pending->isAbandonedExpired() : $pending->isGraceExpired()) {
            try { $pending->delete(); } catch (\Throwable $e) {}
            session()->forget([self::PENDING_ID, self::SESSION_KEY]);
            return null;
        }
        if (!$pending->isVerified() && $pending->otp_expires_at && $pending->otp_expires_at->isPast() && $pending->verified_at === null) {
            // OTP expired does not delete pending but blocks verification; keep pending for resend
        }
        // Prevent tampering: session email must match pending email
        $sessionEmail = session(self::SESSION_KEY . '.email');
        if ($sessionEmail && $sessionEmail !== $pending->email) {
            // Allow authenticated user email to override session tampering check for resume
            $authEmail = $request->user('web')?->email;
            if ($authEmail !== $pending->email) {
                session()->forget([self::PENDING_ID, self::SESSION_KEY]);
                return null;
            }
        }
        return $pending;
    }

    public static function findPendingForUser(\App\Models\User $user): ?PendingRegistration
    {
        return self::findPendingForUserStatic($user);
    }

    public static function resumeRouteForUser(\App\Models\User $user): ?string
    {
        $pending = self::findPendingForUserStatic($user);
        if (!$pending || $pending->isAbandonedExpired()) return null;
        if (empty($pending->organization_data)) return 'register.organization';
        if (empty($pending->address_data)) return 'register.address';
        return null; // completed — no redirect
    }

    protected static function findPendingForUserStatic(\App\Models\User $user): ?PendingRegistration
    {
        $p = PendingRegistration::where('email', $user->email)->whereNotNull('verified_at')->latest('id')->first();
        if (!$p || $p->isAbandonedExpired() || $p->isGraceExpired()) return null;
        return $p;
    }

    protected function detectStep(PendingRegistration $pending): int
    {
        if (!$pending->isVerified()) return 1;
        if (empty($pending->organization_data)) return 2;
        if (empty($pending->address_data)) return 3;
        return 4;
    }

    public static function isOnboardingIncomplete(\App\Models\User $user): bool
    {
        $p = self::findPendingForUserStatic($user);
        return $p !== null && !$p->isAbandonedExpired();
    }

    protected function ensureUserFromPending(PendingRegistration $pending): \App\Models\User
    {
        $existing = \App\Models\User::where('email', $pending->email)->first();
        if ($existing) return $existing;
        $local = explode('@', $pending->email)[0] ?? 'User';
        return \App\Models\User::create([
            'name' => ucfirst($local),
            'first_name' => ucfirst($local),
            'last_name' => '',
            'email' => $pending->email,
            'password_hash' => $pending->password_hash,
            'status' => 'active',
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'preferred_language' => function_exists('mawa_current_lang') ? mawa_current_lang() : 'en',
        ]);
    }

    protected function remainingCooldown(PendingRegistration $pending): int
    {
        if (!$pending->last_sent_at) return 0;
        $cooldown = (int) \App\Support\IdentityConfig::emailOtp('resend_throttle_seconds', 60);
        $elapsed = now()->diffInSeconds($pending->last_sent_at, false);
        // diffInSeconds returns absolute; compute manually
        $elapsed = now()->timestamp - $pending->last_sent_at->timestamp;
        $remaining = $cooldown - $elapsed;
        return $remaining > 0 ? $remaining : 0;
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***';
        $local = $parts[0];
        $domain = $parts[1] ?? '';
        if (strlen($local) <= 2) return str_repeat('*', strlen($local)) . '@' . $domain;
        return substr($local, 0, 1) . str_repeat('*', max(1, strlen($local)-2)) . substr($local, -1) . '@' . $domain;
    }

    protected function geoAddress(string $countryName): ?array
    {
        $country = Country::query()->where('name', $countryName)->where('status', true)->first();
        if ($country === null) return null;
        $labels = \App\Support\GeoHierarchy::levelLabels($country);
        $level1 = \App\Models\AdministrativeUnit::query()
            ->where('country_id', $country->id)->where('status', true)->whereNull('parent_id')
            ->whereHas('level', fn ($q) => $q->where('level_number', 1))->orderBy('name')->get();
        return [
            'country' => $country,
            'country_id' => $country->id,
            'level_labels' => $labels,
            'level1_options' => $level1->pluck('name', 'id')->all(),
            'address_label' => config('geo-labels.localities.'.$country->iso2, config('geo-labels.defaults.locality', 'Address')),
            'zip_first' => $country->iso2 === 'BD',
        ];
    }

    protected function uniqueSlug(string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'institute';
        $slug = $base; $suffix = 2;
        while (Institute::query()->where('slug', $slug)->exists()) { $slug = $base.'-'.$suffix; $suffix++; }
        return $slug;
    }

    protected function assignDefaultLearningStructure(Institute $institute): void
    {
        $existing = \App\Models\InstituteSetting::withoutGlobalScope('institute')->where('institute_id', $institute->id)->value('structure_template_id');
        if ($existing) return;
        $resolved = app(\App\Services\LearningStructureResolver::class)->resolveTemplate($institute);
        $template = $resolved['template'] ?? null;
        if (! $template) return;
        \App\Models\InstituteSetting::withoutGlobalScope('institute')->updateOrCreate(['institute_id' => $institute->id], ['structure_template_id' => $template->id]);
    }

    protected function previewDefaultStructure(array $selection): array
    {
        try {
            $dummy = new Institute(['country' => $selection['country'], 'industry' => $selection['industry'], 'sub_industry' => $selection['sub_industry']]);
            $country = Country::where('name', $selection['country'])->first();
            if ($country) $dummy->country_id = $country->id;
            $resolved = app(\App\Services\LearningStructureResolver::class)->resolveTemplate($dummy);
            $template = $resolved['template'] ?? null;
            if (! $template) return ['template' => null, 'levels' => []];
            $levels = $template->levels()->orderBy('level_order')->get();
            return ['template' => $template, 'levels' => $levels];
        } catch (\Throwable) { return ['template' => null, 'levels' => []]; }
    }
}
