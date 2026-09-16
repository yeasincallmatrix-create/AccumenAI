<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

class DgdaSyncBatch extends Model
{
    protected $fillable = [
        'batch_type',
        'source_file',
        'total_rows',
        'imported',
        'updated',
        'skipped',
        'failed',
        'status',
        'error_log',
        'started_at',
        'completed_at',
        'started_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
