<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Journal line — one debit or one credit on a CoA account. The posting
 * invariant (sum(debit) = sum(credit), no double-sided line) is enforced by
 * JournalPostingService. journal_date is denormalized from the header so ledger
 * queries never join back to journals.
 */
class JournalEntry extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'journal_entries';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'debit' => 'decimal:4',
            'credit' => 'decimal:4',
            'foreign_debit' => 'decimal:4',
            'foreign_credit' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'line_meta' => 'array',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function coa(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'coa_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }
}
