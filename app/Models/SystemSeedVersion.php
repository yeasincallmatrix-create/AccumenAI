<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSeedVersion extends Model
{
    protected $table = 'system_seed_versions';

    protected $guarded = [];

    protected $casts = [
        'executed_at' => 'datetime',
    ];
}
