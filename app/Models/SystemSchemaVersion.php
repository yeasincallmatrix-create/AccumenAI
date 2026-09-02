<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSchemaVersion extends Model
{
    protected $table = 'system_schema_versions';

    protected $guarded = [];

    protected $casts = [
        'installed_at' => 'datetime',
    ];
}
