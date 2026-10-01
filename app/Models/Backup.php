<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Backup extends Model
{
    protected $fillable = [
        'tenant_id', 'owner_user_id', 'destination', 'is_chunked',
        'drive_folder_id', 'drive_file_id',
        'filename', 'size_bytes',
        'file_hmac', 'status', 'error_message', 'completed_at',
        'progress_percent', 'progress_stage', 'progress_message',
        'job_id', 'started_at', 'total_chunks', 'uploaded_chunks',
    ];

    protected $casts = [
        'completed_at'      => 'datetime',
        'started_at'        => 'datetime',
        'size_bytes'        => 'integer',
        'progress_percent'  => 'integer',
        'total_chunks'      => 'integer',
        'uploaded_chunks'   => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Institute::class, 'tenant_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function manifest()
    {
        return $this->hasOne(BackupManifest::class, 'backup_id');
    }

    public function scopeCompleted($q)
    {
        return $q->where('status', 'completed');
    }
}
