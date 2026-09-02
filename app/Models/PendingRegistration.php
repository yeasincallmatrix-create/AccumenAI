<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $table = 'pending_registrations';

    protected $guarded = [];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'organization_data' => 'array',
        'address_data' => 'array',
    ];

    public function isExpired(): bool
    {
        return $this->otp_expires_at !== null && $this->otp_expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function isGraceExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAbandonedExpired(): bool
    {
        if ($this->verified_at === null) return $this->isGraceExpired();
        return $this->verified_at->copy()->addHours(48)->isPast();
    }

    public function state(): string
    {
        if ($this->verified_at === null) {
            if ($this->isGraceExpired()) return 'EXPIRED';
            return 'CREATED';
        }
        // verified
        if ($this->organization_data === null && $this->address_data === null) {
            if ($this->isAbandonedExpired()) return 'ABANDONED';
            return 'OTP_VERIFIED';
        }
        if ($this->isAbandonedExpired()) return 'ABANDONED';
        return 'ONBOARDING';
    }
}
