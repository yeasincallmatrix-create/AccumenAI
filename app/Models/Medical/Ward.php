<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{
    protected $table = 'wards';

    protected $fillable = [
        'institute_id',
        'name',
        'type',
        'total_beds',
        'available_beds',
        'daily_rate',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'total_beds' => 'integer',
        'available_beds' => 'integer',
        'daily_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function beds()
    {
        return $this->hasMany(Bed::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getOccupancyRateAttribute()
    {
        if ($this->total_beds == 0) {
            return 0;
        }

        return round((($this->total_beds - $this->available_beds) / $this->total_beds) * 100, 2);
    }
}
