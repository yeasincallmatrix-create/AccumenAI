<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestorePreview extends Model
{
    protected $fillable = [
        'tenant_id', 'backup_id', 'user_id', 'mode', 'diff',
        'total_insert', 'total_update', 'total_soft_delete', 'total_kept',
        'expires_at', 'confirmed_at',
    ];

    protected $casts = [
        'diff'              => 'array',
        'total_insert'      => 'integer',
        'total_update'      => 'integer',
        'total_soft_delete' => 'integer',
        'total_kept'        => 'integer',
        'expires_at'        => 'datetime',
        'confirmed_at'      => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isUsable(): bool
    {
        return !$this->isExpired() && !$this->isConfirmed();
    }
}
