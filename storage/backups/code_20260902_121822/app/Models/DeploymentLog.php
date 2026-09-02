<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentLog extends Model
{
    protected $table = 'deployment_logs';

    public $timestamps = false;

    protected $fillable = [
        'admin_user_id',
        'type',
        'version',
        'status',
        'log',
        'backup_path',
        'code_backup_path',
        'db_backup_path',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $log) {
            // Keep backup_path and code_backup_path in sync for dual migration compatibility
            if (! empty($log->backup_path) && empty($log->code_backup_path)) {
                $log->code_backup_path = $log->backup_path;
            } elseif (! empty($log->code_backup_path) && empty($log->backup_path)) {
                $log->backup_path = $log->code_backup_path;
            }
        });
    }

    public function getBackupPathAttribute($value): ?string
    {
        if ($value !== null) return $value;
        return $this->attributes['code_backup_path'] ?? null;
    }

    /**
     * Admin user who triggered deployment — polymorphic guard support.
     * For platform_admin guard, resolves to PlatformAdmin.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_user_id');
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRolledBack(): bool
    {
        return $this->status === 'rolled_back';
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
