<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupManifest extends Model
{
    protected $fillable = [
        'backup_id', 'tenant_id', 'drive_file_id', 'checksum_file_id',
        'manifest_json', 'manifest_sha256', 'file_hmac',
        'total_chunks', 'total_size_bytes',
    ];

    protected $casts = [
        'total_chunks' => 'integer',
        'total_size_bytes' => 'integer',
    ];

    public function backup()
    {
        return $this->belongsTo(Backup::class);
    }

    public function chunks()
    {
        return $this->hasMany(BackupChunk::class, 'manifest_id');
    }

    public function getManifestAttribute(): array
    {
        return json_decode($this->manifest_json, true) ?? [];
    }
}
