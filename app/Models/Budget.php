<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use BranchScopedOrShared, SoftDeletes, TenantScoped;

    protected $guarded = [];

    protected $casts = [
        'total_amount' => 'decimal:4',
        'version' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BudgetVersion::class, 'budget_id');
    }

    public function activeVersion(): HasMany
    {
        return $this->hasMany(BudgetVersion::class, 'budget_id')->where('version', $this->version);
    }

    public function latestVersion(): BelongsTo
    {
        return $this->belongsTo(BudgetVersion::class, 'id')->where('version', $this->version);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }
}
