<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupChunk extends Model
{
    protected $fillable = [
        'manifest_id', 'tenant_id', 'content_sha256', 'file_hmac', 'drive_file_id',
        'source_table', 'chunk_index', 'total_chunks', 'size_bytes', 'reference_count',
    ];

    protected $casts = [
        'chunk_index'      => 'integer',
        'total_chunks'     => 'integer',
        'size_bytes'       => 'integer',
        'reference_count'  => 'integer',
    ];

    public function manifest()
    {
        return $this->belongsTo(BackupManifest::class, 'manifest_id');
    }

    public function references()
    {
        return $this->hasMany(BackupChunkReference::class, 'chunk_id');
    }
}
