<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    protected $table = 'prescription_items';

    protected $fillable = [
        'prescription_id',
        'medicine_id',
        'medicine_name',
        'dosage',
        'frequency',
        'duration_days',
        'quantity',
        'special_instructions',
        'status',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'quantity' => 'integer',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function dispenses()
    {
        return $this->hasMany(PharmacyDispense::class, 'prescription_item_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDispensed($query)
    {
        return $query->where('status', 'dispensed');
    }
}
