<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request)
    {
        // Save intended URL
        if ($request->has('redirect')) {
            session(['url.intended' => $request->query('redirect')]);
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::error('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')
                ->with('error', 'Google login failed. Please try again.');
        }

        $email = $googleUser->getEmail();
        if (! $email) {
            return redirect()->route('login')
                ->with('error', 'Google did not provide an email.');
        }

        $user = User::where('email', $email)->first();
        $isNewUser = $user === null;

        if ($user) {
            // Link existing user
            $updates = [];
            if (empty($user->google_id)) {
                $updates['google_id'] = $googleUser->getId();
            }
            if (empty($user->avatar)) {
                $updates['avatar'] = $googleUser->getAvatar();
            }
            if (empty($user->email_verified_at)) {
                $updates['email_verified_at'] = now();
            }
            if (! empty($updates)) {
                $user->update($updates);
            }
        } else {
            // Create new user
            $user = User::create([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'password_hash' => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
                'status' => 'active',
                'account_type' => 'owner',
            ]);
        }

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        if ($isNewUser) {
            // First-time Google signup: Google already verified the email,
            // so skip OTP and drop straight into institution creation
            // (register.organization), reusing the normal onboarding flow.
            $this->resumeOnboarding($this->ensureVerifiedPending($user));

            return redirect()->route('register.organization');
        }

        // Existing user: resume incomplete onboarding (same as password login),
        // otherwise continue to the intended destination.
        if (RegistrationFlowController::isOnboardingIncomplete($user)) {
            $resume = RegistrationFlowController::resumeRouteForUser($user);
            if ($resume) {
                $pending = RegistrationFlowController::findPendingForUser($user);
                if ($pending) {
                    $this->resumeOnboarding($pending);
                }

                return redirect()->route($resume);
            }
        }

        // Safety net: account with no institution at all (created before the
        // onboarding redirect, or institute deleted) → institution creation
        // instead of a broken dashboard. Invited staff always carry a
        // membership row from invite time, so this never misroutes them.
        if (! $user->memberships()->where('status', 'active')->exists()) {
            $this->resumeOnboarding($this->ensureVerifiedPending($user));

            return redirect()->route('register.organization');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Reuse an active verified pending registration, or mint one (Google
     * already verified the email, so no OTP needed) to feed the normal
     * organization → address onboarding steps.
     */
    protected function ensureVerifiedPending(User $user): PendingRegistration
    {
        $pending = PendingRegistration::where('email', $user->email)
            ->whereNotNull('verified_at')
            ->latest('id')
            ->first();
        if ($pending && ! $pending->isAbandonedExpired() && ! $pending->isGraceExpired()) {
            return $pending;
        }

        return PendingRegistration::create([
            'email' => $user->email,
            'password_hash' => $user->getAuthPassword(),
            'verified_at' => now(),
            'expires_at' => now()->addHours(24),
            'attempts' => 0,
            'resend_count' => 0,
        ]);
    }

    protected function resumeOnboarding(PendingRegistration $pending): void
    {
        session([
            RegistrationFlowController::PENDING_ID => $pending->id,
            RegistrationFlowController::SESSION_KEY => [
                'email' => $pending->email,
                'verified' => true,
                'step' => 2,
            ],
        ]);
    }
}
