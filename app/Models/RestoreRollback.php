<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RestoreRollback extends Model
{
    protected $fillable = [
        'tenant_id', 'restore_log_id', 'rollback_token',
        'snapshot_data', 'total_rows', 'expires_at', 'used_at',
    ];

    protected $casts = [
        'snapshot_data' => 'array',
        'total_rows'    => 'integer',
        'expires_at'    => 'datetime',
        'used_at'       => 'datetime',
    ];

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
