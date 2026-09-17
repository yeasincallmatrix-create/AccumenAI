<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class BloodIssueItem extends Model
{
    protected $table = 'blood_issue_items';

    protected $fillable = [
        'blood_request_id', 'blood_unit_id', 'issued_by', 'issued_at',
        'returned_at', 'return_reason', 'status',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public const STATUSES = [
        'issued' => 'Issued',
        'returned' => 'Returned',
    ];

    public function request(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BloodRequest::class, 'blood_request_id');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BloodUnit::class, 'blood_unit_id');
    }

    public function issuedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeIssued($q)
    {
        return $q->where('status', 'issued');
    }

    public function scopeReturned($q)
    {
        return $q->where('status', 'returned');
    }

    public function isReturned(): bool
    {
        return $this->status === 'returned';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'issued' => 'success',
            'returned' => 'warning',
            default => 'secondary',
        };
    }
}
