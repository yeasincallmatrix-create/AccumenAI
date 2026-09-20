<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareCapitalTransaction extends Model
{
    use TenantScoped;

    protected $fillable = [
        'institute_id', 'type',
        'shareholder_id', 'from_shareholder_id', 'to_shareholder_id',
        'shares', 'face_value', 'premium_per_share', 'total_amount',
        'transaction_date', 'certificate_no', 'notes',
        'journal_id', 'created_by',
    ];

    protected $casts = [
        'shares' => 'integer',
        'face_value' => 'decimal:2',
        'premium_per_share' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class);
    }

    public function fromShareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class, 'from_shareholder_id');
    }

    public function toShareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class, 'to_shareholder_id');
    }

    public function scopeIssuances($query)
    {
        return $query->where('type', 'issuance');
    }

    public function scopeTransfers($query)
    {
        return $query->where('type', 'transfer');
    }
}
