<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareCertificate extends Model
{
    use TenantScoped;

    protected $fillable = [
        'institute_id', 'shareholder_id', 'certificate_no',
        'shares', 'face_value', 'total_value',
        'issue_date', 'cancel_date', 'status', 'notes',
    ];

    protected $casts = [
        'shares' => 'integer',
        'face_value' => 'decimal:2',
        'total_value' => 'decimal:2',
        'issue_date' => 'date',
        'cancel_date' => 'date',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
