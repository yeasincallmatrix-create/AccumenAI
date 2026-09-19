<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TenantAccessDenial extends Model
{
    protected $table = 'tenant_access_denials';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'deny_type',
        'deny_key',
        'denied_by',
        'denied_at',
        'expires_at',
        'reason',
        'status',
        'lifted_at',
        'lifted_by',
    ];

    protected $casts = [
        'denied_at' => 'datetime',
        'expires_at' => 'datetime',
        'lifted_at' => 'datetime',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function deniedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'denied_by');
    }

    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'lifted_by');
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
