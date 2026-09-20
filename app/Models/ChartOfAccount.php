<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chart of Accounts ledger account. Replaces the legacy income/expense
 * account_heads: any account type (asset/liability/equity/income/expense) with
 * flag fields for cash, bank, receivable and payable semantics. legacy_head_id
 * maps to the old account_heads row for backfill (weak link, no FK).
 */
class ChartOfAccount extends Model
{
    use BranchScopedOrShared;
    use HasFactory;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'chart_of_accounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'is_bank' => 'boolean',
            'is_receivable' => 'boolean',
            'is_payable' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
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

    public function accountGroup(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function legacyHead(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class, 'legacy_head_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'coa_id');
    }

    public function openingBalances(): HasMany
    {
        return $this->hasMany(OpeningBalance::class, 'coa_id');
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class, 'coa_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }

    /**
     * Asset and expense accounts increase on the debit side; liability, equity
     * and income accounts increase on the credit side.
     */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }

    /**
     * Enable hybrid scope — globals (institute_id NULL) + tenant rows.
     */
    public static function hasGlobalRows(): bool
    {
        return true;
    }

    /**
     * Scope: only global rows.
     */
    public function scopeGlobalOnly($query)
    {
        return $query->withoutGlobalScope('institute')
            ->whereNull('institute_id')
            ->where('is_system', 1);
    }

    /**
     * Scope: only tenant rows (excludes globals).
     */
    public function scopeTenantOnly($query, int $instituteId)
    {
        return $query->withoutGlobalScope('institute')
            ->where('institute_id', $instituteId);
    }

    /**
     * Scope: everything visible to a tenant (globals + own).
     * Same as default scope but explicit for readability.
     */
    public function scopeVisible($query, int $instituteId)
    {
        return $query->withoutGlobalScope('institute')
            ->where(function ($q) use ($instituteId) {
                $q->where(function ($g) {
                    $g->whereNull('institute_id')->where('is_system', 1);
                })->orWhere('institute_id', $instituteId);
            });
    }

    public function isGlobal(): bool
    {
        return is_null($this->institute_id) && (bool) $this->is_system;
    }

    public function isEditableBy(int $instituteId): bool
    {
        return ! $this->isGlobal()
            && (int) $this->institute_id === $instituteId;
    }
}
