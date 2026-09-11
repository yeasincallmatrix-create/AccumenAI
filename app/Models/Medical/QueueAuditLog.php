<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class QueueAuditLog extends Model
{
    protected $fillable = [
        'institute_id',
        'appointment_id',
        'user_id',
        'user_type',
        'actor_name',
        'action',
        'old_order',
        'new_order',
        'amount',
        'needs_verification',
    ];

    protected $casts = [
        'old_order' => 'integer',
        'new_order' => 'integer',
        'amount' => 'decimal:2',
        'needs_verification' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
