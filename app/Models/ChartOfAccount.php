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
     * Per-request cache of institute industry slugs (Phase F).
     *
     * Avoids N+1 when scopeVisible() resolves the tenant's industry on
     * every COA query within the same request.
     *
     * @var array<int, string|null>
     */
    protected static array $industrySlugCache = [];

    /**
     * Resolve an institute's industry slug (Phase F - industry-scoped COA).
     *
     * IMPORTANT: never use `$institute->industry->slug` - the `industry`
     * string column shadows the `industry()` BelongsTo, so `->industry`
     * returns a string, not the related model. Resolve via the FK first
     * with fallback to the string column (covers rows with NULL FK).
     */
    public static function resolveIndustrySlug(int $instituteId): ?string
    {
        if (array_key_exists($instituteId, self::$industrySlugCache)) {
            return self::$industrySlugCache[$instituteId];
        }

        $slug = null;
        try {
            // withTrashed: soft-deleted institutes (e.g. debug rows with NULL
            // FK) still resolve via the string-column fallback.
            $institute = Institute::withTrashed()->find($instituteId, ['id', 'industry_id', 'industry']);
            if ($institute) {
                $slug = \App\Models\Industry::where('id', $institute->industry_id)->value('slug')
                    ?? ($institute->getAttribute('industry') ?: null);
            }
        } catch (\Throwable) {
            $slug = null;
        }

        return self::$industrySlugCache[$instituteId] = $slug;
    }

    /** Clear the per-request industry slug cache (tests). */
    public static function clearIndustrySlugCache(): void
    {
        self::$industrySlugCache = [];
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
     * Scope: only global rows (administrative - bypasses industry filter).
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
     *
     * Phase F: global system rows are narrowed to universal
     * (industries NULL) + the tenant's own industry slug. Tenant-owned
     * rows are always visible. The default TenantScoped hybrid branch is
     * intentionally untouched - reports/postings keep unfiltered access.
     *
     * NOTE: scope name is 'institute' (per TenantScoped).
     * `visibleTo` is the canonical Phase-D name; `visible` is kept
     * as an alias (service layer already calls ::visible()).
     */
    public function scopeVisible($query, int $instituteId)
    {
        $slug = static::resolveIndustrySlug($instituteId);

        try {
            $hasIndustries = \Illuminate\Support\Facades\Schema::hasColumn('chart_of_accounts', 'industries');
        } catch (\Throwable) {
            $hasIndustries = false;
        }

        // Pre-Phase-F schema: no industry filtering possible.
        if (! $hasIndustries) {
            return $query->withoutGlobalScope('institute')
                ->where(function ($q) use ($instituteId) {
                    $q->where(function ($g) {
                        $g->whereNull('institute_id')->where('is_system', 1);
                    })->orWhere('institute_id', $instituteId);
                });
        }

        return $query->withoutGlobalScope('institute')
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
