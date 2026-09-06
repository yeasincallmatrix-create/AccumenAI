<?php

namespace App\Models\Medical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class VitalSign extends Model
{
    protected $table = 'vital_signs';

    protected $fillable = [
        'admission_id',
        'temperature',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'pulse',
        'respiratory_rate',
        'spo2',
        'blood_sugar',
        'weight',
        'height',
        'recorded_by',
        'recorded_at',
        'notes',
    ];

    protected $casts = [
        'temperature' => 'decimal:1',
        'blood_pressure_systolic' => 'integer',
        'blood_pressure_diastolic' => 'integer',
        'pulse' => 'integer',
        'respiratory_rate' => 'integer',
        'spo2' => 'integer',
        'blood_sugar' => 'decimal:1',
        'weight' => 'decimal:1',
        'height' => 'decimal:1',
        'recorded_at' => 'datetime',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getBloodPressureAttribute()
    {
        if ($this->blood_pressure_systolic && $this->blood_pressure_diastolic) {
            return $this->blood_pressure_systolic.'/'.$this->blood_pressure_diastolic;
        }

        return null;
    }

    public function getBmiAttribute()
    {
        if ($this->weight && $this->height) {
            $heightInMeters = $this->height / 100;

            return round($this->weight / ($heightInMeters * $heightInMeters), 1);
        }

        return null;
    }
}
