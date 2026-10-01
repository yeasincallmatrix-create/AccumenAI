<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestoreLog extends Model
{
    protected $fillable = [
        'tenant_id', 'backup_id', 'user_id', 'mode',
        'records_affected', 'rollback_path', 'rollback_expires_at',
        'status', 'completed_at',
    ];

    protected $casts = [
        'records_affected'     => 'array',
        'completed_at'         => 'datetime',
        'rollback_expires_at'  => 'datetime',
    ];
}
