<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

class DoctorAvailability extends Model
{
    protected $table = 'doctor_availabilities';

    protected $fillable = [
        'doctor_id', 'day_of_week', 'start_time', 'end_time',
        'slot_duration', 'room_no', 'is_available', 'notes',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'slot_duration' => 'integer',
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
}
