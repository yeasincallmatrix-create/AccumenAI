<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestoreToken extends Model
{
    protected $fillable = [
        'tenant_id', 'backup_id', 'user_id',
        'token_hash', 'expires_at', 'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function scopeActive($q)
    {
        return $q->whereNull('used_at')->where('expires_at', '>', now());
    }
}
