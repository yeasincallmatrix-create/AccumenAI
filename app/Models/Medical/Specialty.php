<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class Specialty extends Model
{
    protected $table = 'medical_specialties';

    protected $fillable = [
        'institute_id', 'department_id', 'name', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
