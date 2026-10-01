<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestoreLog extends Model
{
    protected $fillable = [
        'tenant_id', 'backup_id', 'user_id', 'mode',
        'records_affected', 'rollback_path', 'rollback_expires_at',
        'status', 'completed_at',
        'progress_percent', 'progress_stage', 'progress_message',
        'job_id', 'downloaded_chunks', 'total_chunks', 'error_message',
    ];

    protected $casts = [
        'records_affected'     => 'array',
        'completed_at'         => 'datetime',
        'rollback_expires_at'  => 'datetime',
        'progress_percent'     => 'integer',
        'downloaded_chunks'    => 'integer',
        'total_chunks'         => 'integer',
    ];
}
