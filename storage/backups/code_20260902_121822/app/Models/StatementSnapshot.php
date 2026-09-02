<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable frozen copy of an official financial statement. Snapshots are never
 * mutated; regeneration inserts a new row keyed by as_of_date + generated_at.
 */
class StatementSnapshot extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;

    protected $table = 'statement_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'payload' => 'array',
            'locked' => 'boolean',
            'generated_at' => 'datetime',
        ];
    }

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

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'generated_by');
    }
}
