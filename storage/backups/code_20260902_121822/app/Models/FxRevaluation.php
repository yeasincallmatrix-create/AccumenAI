<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FX period-end revaluation record (STEP 19).
 *
 * One row per (institute, branch, fiscal year, period, currency, as-of date) —
 * the unique business key makes revaluation idempotent. The row keeps the
 * carrying value, closing rate, revalued value and difference, and links the
 * adjustment journal it posted. Rows are never deleted; a corrected
 * revaluation is reversed through the journal reversal convention and the row
 * is marked `reversed`.
 */
class FxRevaluation extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;

    protected $table = 'fx_revaluations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'closing_rate' => 'decimal:8',
            'carrying_value' => 'decimal:4',
            'revalued_value' => 'decimal:4',
            'difference' => 'decimal:4',
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }
}
