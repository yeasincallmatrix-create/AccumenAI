<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PharmacyDispense extends Model
{
    protected $table = 'pharmacy_dispenses';

    protected $fillable = [
        'institute_id',
        'branch_id',
        'prescription_item_id',
        'stock_id',
        'quantity_dispensed',
        'dispensed_by',
        'dispense_date',
        'notes',
    ];

    protected $casts = [
        'quantity_dispensed' => 'integer',
        'dispense_date' => 'date',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function prescriptionItem()
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function stock()
    {
        return $this->belongsTo(PharmacyStock::class, 'stock_id');
    }

    public function dispensedBy()
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
}
