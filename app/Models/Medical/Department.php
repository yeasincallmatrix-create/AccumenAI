<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'medical_departments';

    protected $fillable = [
        'institute_id', 'name', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function specialties()
    {
        return $this->hasMany(Specialty::class);
    }

    public function doctors()
    {
        return $this->hasManyThrough(Doctor::class, Specialty::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
