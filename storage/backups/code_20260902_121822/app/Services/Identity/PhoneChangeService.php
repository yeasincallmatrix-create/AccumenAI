<?php

namespace App\Services\Identity;

use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Validation\ValidationException;

class PhoneChangeService
{
    public function __construct(protected PhoneOtpService $otpService) {}

    public function requestChange(User $user, string $newPhone, ?string $country = null): void
    {
        $normalized = PhoneNormalizer::toE164($newPhone, $country);
        if ($normalized === null) {
            throw ValidationException::withMessages(['phone' => ['Invalid phone number.']]);
        }

        if ($user->phone && $normalized === $user->phone) {
            throw ValidationException::withMessages(['phone' => ['New phone must be different.']]);
        }

        $exists = User::where('phone', $normalized)->where('id','!=',$user->id)->exists();
        if ($exists) {
            throw ValidationException::withMessages(['phone' => ['Phone already taken.']]);
        }

        // Also check pending_phone uniqueness
        $pendingExists = User::where('pending_phone', $normalized)->where('id','!=',$user->id)->exists();
        if ($pendingExists) {
            throw ValidationException::withMessages(['phone' => ['Phone already taken.']]);
        }

        $user->forceFill(['pending_phone' => $normalized])->save();
        $this->otpService->send($user, $normalized, $country);

        IdentityAuditService::log($user->id, 'phone_change_requested', 'phone', ['new_phone' => $this->mask($normalized)]);
    }

    public function verifyChange(User $user, string $otp, ?string $country = null): bool
    {
        if ($user->pending_phone === null) {
            throw ValidationException::withMessages(['phone' => ['No pending phone change.']]);
        }
        $phone = $user->pending_phone;

        // Use otp service to verify
        $this->otpService->verify($user, $phone, $otp, $country);

        // Race uniqueness again
        $exists = User::where('phone', $phone)->where('id','!=',$user->id)->exists();
        if ($exists) {
            $user->forceFill(['pending_phone' => null])->save();
            throw ValidationException::withMessages(['phone' => ['Phone already taken.']]);
        }

        $oldPhone = $user->phone;
        $user->forceFill([
            'phone' => $phone,
            'phone_verified_at' => now(),
            'pending_phone' => null,
        ])->save();

        IdentityAuditService::log($user->id, 'phone_changed', 'phone', ['old' => $oldPhone ? $this->mask($oldPhone) : null, 'new' => $this->mask($phone)]);

        return true;
    }

    protected function mask(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) return str_repeat('*', $len);
        return substr($phone,0,3).str_repeat('*',$len-6).substr($phone,-3);
    }
}
