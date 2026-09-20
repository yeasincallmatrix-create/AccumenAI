<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partner extends Model
{
    use TenantScoped;

    protected $fillable = [
        'institute_id', 'name', 'email', 'phone', 'nid', 'address',
        'capital', 'share_percent',
        'capital_account_id', 'drawing_account_id',
        'joined_at', 'is_active',
    ];

    protected $casts = [
        'capital' => 'decimal:2',
        'share_percent' => 'decimal:2',
        'joined_at' => 'date',
        'is_active' => 'boolean',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function capitalAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'capital_account_id');
    }

    public function drawingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'drawing_account_id');
    }

    public function getFormattedCapitalAttribute(): string
    {
        return number_format($this->capital, 2);
    }

    public function hasAccountLinks(): bool
    {
        return $this->capital_account_id && $this->drawing_account_id;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
