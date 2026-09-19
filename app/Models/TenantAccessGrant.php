<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TenantAccessGrant extends Model
{
    protected $table = 'tenant_access_grants';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'grant_type',
        'grant_key',
        'granted_by',
        'granted_at',
        'expires_at',
        'reason',
        'status',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'revoked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }
}
