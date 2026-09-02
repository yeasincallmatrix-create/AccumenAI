<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandLog extends Model
{
    protected $table = 'command_logs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'exit_code' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'admin_id');
    }
}
