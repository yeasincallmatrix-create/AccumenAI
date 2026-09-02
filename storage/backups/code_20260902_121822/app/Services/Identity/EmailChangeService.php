<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Support\EmailNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailChangeService
{
    public function requestChange(User $user, string $newEmail): void
    {
        $normalized = EmailNormalizer::normalize($newEmail);
        if ($normalized === null || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => ['Invalid email address.']]);
        }

        EmailDomainPolicy::validateOrFail($normalized);

        // Uniqueness includes pending_email? Check against users.email and users.pending_email
        $exists = User::where('email', $normalized)->where('id', '!=', $user->id)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['email' => ['Email already taken.']]);
        }
        $pendingExists = User::where('pending_email', $normalized)->where('id', '!=', $user->id)->exists();
        if ($pendingExists) {
            throw ValidationException::withMessages(['email' => ['Email already taken.']]);
        }

        if ($normalized === strtolower(trim($user->email))) {
            throw ValidationException::withMessages(['email' => ['New email must be different.']]);
        }

        // Throttle: simple cache per user
        $throttleKey = 'email_change:'.$user->id;
        if (cache()->has($throttleKey)) {
            throw ValidationException::withMessages(['email' => ['Please wait before requesting another change.']]);
        }

        $token = Str::random(64);
        $hash = Hash::make($token);
        $expires = (int) config('identity.email_change.expires_minutes', 60);

        $user->forceFill([
            'pending_email' => $normalized,
            'pending_email_token_hash' => $hash,
            'pending_email_expires_at' => now()->addMinutes($expires),
        ])->save();

        // Generate verification URL (signed)
        $verifyUrl = route('account.email.verify', ['token' => $token, 'email' => $normalized]);

        // Send verification email (via Mail::raw for simplicity, or use notification)
        try {
            Mail::raw("Verify your new email by visiting: {$verifyUrl}", function ($msg) use ($normalized) {
                $msg->to($normalized)->subject('Verify your new email');
            });
        } catch (\Throwable $e) {
            report($e);
        }

        cache()->put($throttleKey, 1, (int) config('identity.email_change.throttle_seconds', 60));

        IdentityAuditService::log($user->id, 'email_change_requested', 'email', ['new_email' => $this->maskEmail($normalized)]);
    }

    public function verify(User $user, string $token, string $email): bool
    {
        if ($user->pending_email === null || $user->pending_email_token_hash === null) {
            throw ValidationException::withMessages(['email' => ['No pending email change.']]);
        }

        if ($user->pending_email_expires_at && $user->pending_email_expires_at->isPast()) {
            $this->clearPending($user);
            throw ValidationException::withMessages(['email' => ['Verification expired.']]);
        }

        $normalizedRequest = EmailNormalizer::normalize($email);
        if ($normalizedRequest !== strtolower(trim($user->pending_email))) {
            throw ValidationException::withMessages(['email' => ['Invalid verification.']]);
        }

        if (! Hash::check($token, $user->pending_email_token_hash)) {
            IdentityAuditService::log($user->id, 'email_change_failed', 'email', []);
            throw ValidationException::withMessages(['token' => ['Invalid token.']]);
        }

        // Ensure uniqueness again at verify time (race)
        $exists = User::where('email', $user->pending_email)->where('id', '!=', $user->id)->exists();
        if ($exists) {
            $this->clearPending($user);
            throw ValidationException::withMessages(['email' => ['Email already taken.']]);
        }

        $oldEmail = $user->email;
        $newEmail = $user->pending_email;

        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => now(),
            'pending_email' => null,
            'pending_email_token_hash' => null,
            'pending_email_expires_at' => null,
        ])->save();

        IdentityAuditService::log($user->id, 'email_changed', 'email', ['old' => $this->maskEmail($oldEmail), 'new' => $this->maskEmail($newEmail)]);

        // Security notification to old email (if mail configured)
        try {
            Mail::raw("Your email was changed from {$oldEmail} to {$newEmail}. If you did not do this, contact support.", function ($m) use ($oldEmail) {
                $m->to($oldEmail)->subject('Your email was changed');
            });
        } catch (\Throwable $e) {
            report($e);
        }

        return true;
    }

    protected function clearPending(User $user): void
    {
        $user->forceFill([
            'pending_email' => null,
            'pending_email_token_hash' => null,
            'pending_email_expires_at' => null,
        ])->save();
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return '***';
        $local = $parts[0];
        $domain = $parts[1];
        $maskedLocal = strlen($local) > 2 ? substr($local,0,1).str_repeat('*', strlen($local)-2).substr($local,-1) : str_repeat('*', strlen($local));
        return $maskedLocal.'@'.$domain;
    }
}
