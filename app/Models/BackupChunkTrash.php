<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupChunkTrash extends Model
{
    protected $table = 'backup_chunk_trash';

    protected $fillable = [
        'tenant_id', 'drive_file_id', 'content_sha256', 'size_bytes',
        'trashed_at', 'expires_at',
    ];

    protected $casts = [
        'trashed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
