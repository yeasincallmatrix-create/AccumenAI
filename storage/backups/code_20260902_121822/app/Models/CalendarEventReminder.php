<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEventReminder extends Model
{
    protected $table = 'calendar_event_reminders';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'minutes_before' => 'integer',
        'is_sent' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'user_id');
    }
}
