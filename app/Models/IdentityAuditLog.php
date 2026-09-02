<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityAuditLog extends Model
{
    protected $table = 'identity_audit_logs';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];
}
