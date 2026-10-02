<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestoreAudit extends Model
{
    protected $fillable = [
        'tenant_id', 'restore_log_id', 'user_id', 'mode',
        'affected_tables', 'total_affected', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'affected_tables' => 'array',
        'total_affected'  => 'integer',
    ];
}
