<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Phone2faOtp extends Model
{
    protected $table = 'phone_2fa_otps';

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
