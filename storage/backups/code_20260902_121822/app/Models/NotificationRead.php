<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRead extends Model
{
    protected $table = 'notification_reads';

    public $timestamps = false;

    protected $guarded = [];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
