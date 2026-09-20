<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dividend extends Model
{
    use TenantScoped;

    protected $fillable = [
        'institute_id', 'reference_no',
        'declared_date', 'record_date', 'payment_date',
        'financial_year', 'total_dividend', 'per_share_amount',
        'total_shares', 'total_tax', 'total_net',
        'status', 'board_resolution', 'notes',
        'journal_id', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'declared_date' => 'date',
        'record_date' => 'date',
        'payment_date' => 'date',
        'total_dividend' => 'decimal:2',
        'per_share_amount' => 'decimal:4',
        'total_shares' => 'integer',
        'total_tax' => 'decimal:2',
        'total_net' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(DividendPayout::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeDeclared($query)
    {
        return $query->where('status', 'declared');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isDeclared(): bool
    {
        return $this->status === 'declared';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['draft']);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['draft', 'declared']);
    }
}
