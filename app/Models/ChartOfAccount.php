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
            'industries' => 'array',
        ];
    }

    /**
     * Phase F — industry enforcement on the DEFAULT query path.
     *
     * TenantScoped's 'institute' scope allows all global rows; this second
     * scope narrows globals to universal (industries NULL) + the tenant's
     * own industry slug. Tenant rows always pass. Skipped when there is no
     * tenant context (admin/system) or the column hasn't migrated yet.
     *
     * SPLIT (documented): resolvers (accountByCode) match tenant copies
     * first and are unaffected; browsing (list UI) and visibleTo() callers
     * see the industry-filtered global set.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('industry', function ($builder) {
            $instituteId = \App\Support\TenantContext::id();
            if (! $instituteId) {
                return;
            }

            try {
                if (! \Illuminate\Support\Facades\Schema::hasColumn('chart_of_accounts', 'industries')) {
                    return;
                }
            } catch (\Throwable) {
                return;
            }

            $slug = static::industrySlugFor($instituteId);

            // Constrain ONLY global rows; every tenant row passes here and
            // stays governed by the 'institute' scope. This preserves
            // explicit cross-tenant lookups that bypass 'institute'
            // (policy then answers 403 instead of binding 404).
            $builder->where(function ($q) use ($slug) {
                $q->whereNotNull('chart_of_accounts.institute_id')
                    ->orWhere(function ($g) use ($slug) {
                        $g->whereNull('chart_of_accounts.institute_id')
                            ->where(function ($inner) use ($slug) {
                                $inner->whereNull('chart_of_accounts.industries');
                                if ($slug) {
                                    $inner->orWhereJsonContains('chart_of_accounts.industries', $slug);
                                }
                            });
                    });
            });
        });
    }

    /**
     * Cached industry slug for an institute (per request).
     *
     * NOTE: Institute has a string `industry` column that shadows the
     * `industry()` relation — query Industry directly by industry_id.
     */
    public static function industrySlugFor(int $instituteId): ?string
    {
        static $cache = [];

        if (! array_key_exists($instituteId, $cache)) {
            try {
                $industryId = Institute::whereKey($instituteId)->value('industry_id');
                $cache[$instituteId] = $industryId
                    ? \App\Models\Industry::whereKey($industryId)->value('slug')
                    : null;
            } catch (\Throwable) {
                $cache[$instituteId] = null;
            }
        }

        return $cache[$instituteId];
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
     * Scope: only global rows (administrative — bypasses industry filter).
     */
    public function scopeGlobalOnly($query)
    {
        return $query->withoutGlobalScope('institute')
            ->withoutGlobalScope('industry')
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
     *
     * NOTE: scope name is 'institute' (per TenantScoped).
     * `visibleTo` is the canonical Phase-D name; `visible` is kept
     * as an alias (service layer already calls ::visible()).
     */
    public function scopeVisible($query, int $instituteId)
    {
        $slug = static::industrySlugFor($instituteId);

        try {
            $hasIndustries = \Illuminate\Support\Facades\Schema::hasColumn('chart_of_accounts', 'industries');
        } catch (\Throwable) {
            $hasIndustries = false;
        }

        // Pre-Phase-F schema: no industry filtering possible.
        if (! $hasIndustries) {
            return $query->withoutGlobalScope('institute')
                ->withoutGlobalScope('industry')
                ->where(function ($q) use ($instituteId) {
                    $q->where(function ($g) {
                        $g->whereNull('institute_id')->where('is_system', 1);
                    })->orWhere('institute_id', $instituteId);
                });
        }

        return $query->withoutGlobalScope('institute')
            ->withoutGlobalScope('industry')
            ->where(function ($q) use ($instituteId, $slug) {
                $q->where(function ($g) use ($slug) {
                    $g->whereNull('institute_id')->where('is_system', 1)
                        ->where(function ($inner) use ($slug) {
                            $inner->whereNull('industries');
                            if ($slug) {
                                $inner->orWhereJsonContains('industries', $slug);
                            }
                        });
                })->orWhere('institute_id', $instituteId);
            });
    }

    /**
     * Canonical alias of scopeVisible().
     *
     * Usage: ChartOfAccount::visibleTo($tenantId)->...
     */
    public function scopeVisibleTo($query, int $instituteId)
    {
        return $this->scopeVisible($query, $instituteId);
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

    /**
     * Phase F helper: is this row visible to an industry slug?
     * Tenant rows and universal (NULL) globals always pass.
     */
    public function isVisibleToIndustry(?string $industrySlug): bool
    {
        if ($this->institute_id !== null) {
            return true;
        }
        if (empty($this->industries)) {
            return true;
        }

        return $industrySlug !== null && in_array($industrySlug, (array) $this->industries, true);
    }

    /**
     * Phase F helper: constrain a global-rows query to an industry.
     */
    public function scopeForIndustry($query, ?string $industrySlug)
    {
        return $query->where(function ($q) use ($industrySlug) {
            $q->whereNull('industries');
            if ($industrySlug) {
                $q->orWhereJsonContains('industries', $industrySlug);
            }
        });
    }
}
