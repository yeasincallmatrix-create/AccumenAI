<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupVerificationLog extends Model
{
    protected $table = 'backup_verification_logs';

    protected $guarded = [];

    protected $casts = [
        'report' => 'array',
        'verified_at' => 'datetime',
    ];
}
