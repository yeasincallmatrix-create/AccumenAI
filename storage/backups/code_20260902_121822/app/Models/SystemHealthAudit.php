<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHealthAudit extends Model
{
    protected $table = 'system_health_audits';

    protected $guarded = [];

    protected $casts = [
        'checks' => 'array',
        'missing_tables' => 'array',
        'missing_seeds' => 'array',
        'orphans' => 'array',
        'missing_indexes' => 'array',
        'score' => 'integer',
    ];
}
