<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemBackup extends Model
{
    protected $table = 'system_backups';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'size_bytes' => 'integer',
        'migration_count' => 'integer',
        'table_count' => 'integer',
    ];
}
