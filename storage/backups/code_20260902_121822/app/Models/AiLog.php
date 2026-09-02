<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLog extends Model
{
    public $timestamps = false;

    protected $table = 'ai_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
