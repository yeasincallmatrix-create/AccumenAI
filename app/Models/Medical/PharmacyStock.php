<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class PharmacyStock extends Model
{
    protected $table = 'pharmacy_stock';

    protected $fillable = [
        'institute_id',
        'medicine_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity_received',
        'current_quantity',
        'purchase_price',
        'selling_price',
        'supplier_invoice_no',
        'received_date',
        'notes',
    ];

    protected $casts = [
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'received_date' => 'date',
        'quantity_received' => 'integer',
        'current_quantity' => 'integer',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('current_quantity', '>', 0)
            ->where('expiry_date', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now());
    }

    public function scopeNearExpiry($query, $days = 30)
    {
        return $query->where('expiry_date', '>', now())
            ->where('expiry_date', '<', now()->addDays($days));
    }

    public function getIsExpiredAttribute()
    {
        return $this->expiry_date < now();
    }

    public function getDaysToExpiryAttribute()
    {
        return now()->diffInDays($this->expiry_date, false);
    }
}
