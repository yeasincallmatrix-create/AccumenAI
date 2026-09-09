<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class PatientAllergy extends Model
{
    protected $fillable = [
        'institute_id',
        'patient_id',
        'medicine_id',
        'allergen_type',
        'allergen_name',
        'reaction',
        'severity',
        'is_verified',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
