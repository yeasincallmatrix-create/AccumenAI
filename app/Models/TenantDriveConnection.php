<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantDriveConnection extends Model
{
    protected $fillable = [
        'tenant_id', 'connected_by_user_id', 'google_user_email',
        'google_user_id', 'refresh_token', 'drive_folder_id',
        'app_folder_id', 'chunks_folder_id', 'manifests_folder_id', 'trash_folder_id',
        'connected_at', 'last_sync_at', 'revoked_at',
    ];

    protected $casts = [
        'connected_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    // Encrypt refresh_token at rest
    protected $hidden = ['refresh_token'];

    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = encrypt($value);
    }

    public function getRefreshTokenAttribute($value)
    {
        return $value ? decrypt($value) : null;
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function tenant()
    {
        return $this->belongsTo(Institute::class, 'tenant_id');
    }
}
