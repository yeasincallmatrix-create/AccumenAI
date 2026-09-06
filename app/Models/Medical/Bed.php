<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    protected $table = 'beds';

    protected $fillable = [
        'institute_id',
        'ward_id',
        'bed_number',
        'status',
        'notes',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }
}
