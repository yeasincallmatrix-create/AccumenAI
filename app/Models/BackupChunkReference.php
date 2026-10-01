<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupChunkReference extends Model
{
    protected $table = 'backup_chunk_references';

    protected $fillable = [
        'tenant_id', 'manifest_id', 'chunk_id', 'content_sha256', 'drive_file_id',
    ];

    protected $casts = [
        'manifest_id' => 'integer',
        'chunk_id'    => 'integer',
        'tenant_id'   => 'integer',
    ];

    public function manifest()
    {
        return $this->belongsTo(BackupManifest::class, 'manifest_id');
    }

    public function chunk()
    {
        return $this->belongsTo(BackupChunk::class, 'chunk_id');
    }
}
