<?php

namespace App\Services\Auth;

use App\Models\BannedEmail;
use Illuminate\Support\Facades\Log;

class EmailBanService
{
    public function isBanned(?string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        return BannedEmail::active()
            ->where('email', BannedEmail::normalizeEmail($email))
            ->exists();
    }

    public function getBan(?string $email): ?BannedEmail
    {
        if (empty($email)) {
            return null;
        }

        return BannedEmail::active()
            ->where('email', BannedEmail::normalizeEmail($email))
            ->first();
    }

    public function ban(
        string $email,
        ?string $reason = null,
        ?int $bannedBy = null,
        bool $isPermanent = true,
        ?\DateTimeInterface $expiresAt = null
    ): BannedEmail {
        $normalized = BannedEmail::normalizeEmail($email);

        $ban = BannedEmail::updateOrCreate(
            ['email' => $normalized],
            [
                'reason' => $reason,
                'banned_by' => $bannedBy,
                'banned_at' => now(),
                'is_permanent' => $isPermanent,
                'expires_at' => $isPermanent ? null : $expiresAt,
            ]
        );

        Log::info('Email banned', [
            'email' => $normalized,
            'reason' => $reason,
            'is_permanent' => $isPermanent,
        ]);

        return $ban;
    }

    public function unban(string $email): bool
    {
        $deleted = BannedEmail::where('email', BannedEmail::normalizeEmail($email))
            ->delete();

        if ($deleted > 0) {
            Log::info('Email unbanned', ['email' => $email]);
        }

        return $deleted > 0;
    }

    public function getBanReason(string $email): ?string
    {
        $ban = $this->getBan($email);
        if (! $ban) {
            return null;
        }

        return $ban->reason ?? ($ban->is_permanent
            ? 'This email has been permanently banned.'
            : 'This email is temporarily suspended.');
    }
}
