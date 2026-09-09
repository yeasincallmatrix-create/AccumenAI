<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Admission extends Model
{
    use SoftDeletes;

    protected $table = 'admissions';

    protected $fillable = [
        'institute_id',
        'patient_id',
        'bed_id',
        'admitting_doctor_id',
        'admission_date',
        'admission_time',
        'primary_diagnosis',
        'secondary_diagnosis',
        'status',
        'discharge_date',
        'discharge_time',
        'discharge_summary',
        'notes',
        'discharged_by',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'discharge_date' => 'date',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class);
    }

    public function admittingDoctor()
    {
        return $this->belongsTo(User::class, 'admitting_doctor_id');
    }

    public function dischargedBy()
    {
        return $this->belongsTo(User::class, 'discharged_by');
    }

    public function vitalSigns()
    {
        return $this->hasMany(VitalSign::class, 'admission_id');
    }

    public function nursingNotes()
    {
        return $this->hasMany(NursingNote::class, 'admission_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getLengthOfStayAttribute()
    {
        if ($this->discharge_date) {
            return $this->admission_date->diffInDays($this->discharge_date);
        }

        return $this->admission_date->diffInDays(now());
    }
}
