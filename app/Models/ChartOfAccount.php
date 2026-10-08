<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

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

    public const TYPE_ORDER = [
        'asset' => 1,
        'liability' => 2,
        'equity' => 3,
        'income' => 4,
        'expense' => 5,
    ];

    /**
     * Tables holding RESTRICT foreign keys to chart_of_accounts.id. Each is
     * checked before delete so a referenced account is blocked with a clear
     * DomainException instead of a raw SQLSTATE 23000 from the database.
     */
    private const DELETE_BLOCKERS = [
        'journal_entries' => ['label' => 'journal entries', 'columns' => ['coa_id']],
        'budget_lines' => ['label' => 'budget lines', 'columns' => ['coa_id']],
        'expenses' => ['label' => 'expenses', 'columns' => ['expense_account_id', 'payment_account_id']],
        'opening_balances' => ['label' => 'opening balances', 'columns' => ['coa_id']],
    ];

    protected $table = 'chart_of_accounts';

    /**
     * Mass-assignable columns. Ownership and audit columns are deliberately
     * omitted: institute_id/branch_id stay fillable only because the
     * TenantScoped/BranchScopedOrShared traits force them during onboarding.
     */
    protected $fillable = [
        'institute_id',
        'branch_id',
        'parent_id',
        'account_group_id',
        'code',
        'name',
        'type',
        'cash_flow_category',
        'currency_id',
        'industries',
        'is_cash',
        'is_bank',
        'is_receivable',
        'is_payable',
        'is_active',
        'is_header',
        'is_postable',
    ];

    protected static function booted(): void
    {
        static::creating(function (ChartOfAccount $account) {
            $parentId = $account->getAttribute('parent_id');

            if ($parentId) {
                static::promoteParentFlags((int) $parentId);
            }
        });

        static::updating(function (ChartOfAccount $account) {
            if ($account->isDirty('parent_id')) {
                $oldParentId = $account->getOriginal('parent_id');
                $newParentId = $account->getAttribute('parent_id');
                $hasChildren = $account->children()->exists();

                if ($hasChildren) {
                    $account->is_header = true;
                    $account->is_postable = false;
                } else {
                    $account->is_header = false;
                    $account->is_postable = true;
                }

                if ($oldParentId) {
                    static::recountParentFlags((int) $oldParentId);
                }
                if ($newParentId) {
                    static::promoteParentFlags((int) $newParentId);
                }
            }
        });

        static::updating(function (ChartOfAccount $acc) {
            if ($acc->is_system && $acc->isDirty([
                'code', 'name', 'type', 'parent_id',
                'industries', 'is_header', 'is_postable',
            ])) {
                throw new \DomainException("Cannot modify system account: {$acc->code}");
            }
        });

        static::deleting(function (ChartOfAccount $acc) {
            if ($acc->is_system) {
                throw new \DomainException("Cannot delete system account: {$acc->code}");
            }

            $blocker = static::deleteBlockerLabel($acc->id);
            if ($blocker !== null) {
                throw new \DomainException("Cannot delete account with {$blocker}: {$acc->code}");
            }
        });
    }

    /**
     * A row that has just gained a child becomes a header: never postable.
     * Locked rows (is_system) and missing/soft-deleted rows are left alone so
     * seeded global anchors keep the flags their seeder gave them.
     */
    private static function promoteParentFlags(int $parentId): void
    {
        $parent = static::withoutGlobalScopes()
            ->where('id', $parentId)
            ->whereNull('deleted_at')
            ->first();

        if ($parent === null || $parent->is_system) {
            return;
        }

        $parent->update([
            'is_header' => true,
            'is_postable' => false,
        ]);
    }

    /**
     * Recount a former parent's children and keep its header/postable flags
     * honest. Counts run without global scopes so a tenant view can never
     * under-count siblings belonging to another visibility slice.
     */
    private static function recountParentFlags(int $parentId): void
    {
        $parent = static::withoutGlobalScopes()
            ->where('id', $parentId)
            ->whereNull('deleted_at')
            ->first();

        if ($parent === null || $parent->is_system) {
            return;
        }

        $children = static::withoutGlobalScopes()
            ->where('parent_id', $parentId)
            ->whereNull('deleted_at')
            ->count();

        $parent->update([
            'is_header' => $children > 0,
            'is_postable' => $children === 0,
        ]);
    }

    public function isLocked(): bool
    {
        return (bool) $this->is_system;
    }

    public function isEditable(): bool
    {
        return ! $this->is_system;
    }

    public function canBeDeleted(): bool
    {
        if ($this->is_system) {
            return false;
        }
        if ($this->children()->exists()) {
            return false;
        }

        return static::deleteBlockerLabel($this->id) === null;
    }

    /**
     * Label of the first RESTRICT-referencing row set touching this account,
     * or null when nothing blocks the delete.
     */
    private static function deleteBlockerLabel(int $accountId): ?string
    {
        foreach (self::DELETE_BLOCKERS as $table => $blocker) {
            $exists = \DB::table($table)
                ->where(function ($query) use ($blocker, $accountId) {
                    foreach ($blocker['columns'] as $index => $column) {
                        $index === 0
                            ? $query->where($column, $accountId)
                            : $query->orWhere($column, $accountId);
                    }
                })
                ->exists();

            if ($exists) {
                return $blocker['label'];
            }
        }

        return null;
    }

    public function scopeEditable($q)
    {
        return $q->where('is_system', false);
    }

    public function scopeLocked($q)
    {
        return $q->where('is_system', true);
    }

    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'is_bank' => 'boolean',
            'is_receivable' => 'boolean',
            'is_payable' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'is_postable' => 'boolean',
            'is_header' => 'boolean',
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
                $slug = Industry::where('id', $institute->industry_id)->value('slug')
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
            $hasIndustries = Schema::hasColumn('chart_of_accounts', 'industries');
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

    public function scopePostable($query)
    {
        return $query->where('is_postable', true);
    }

    public function scopeHeaders($query)
    {
        return $query->where('is_header', true);
    }

    /**
     * Canonical display order: type (asset→liability→equity→income→expense),
     * then natural code order (1000 before 1000.1 before 1000.2 before 1000.10),
     * with anchors appearing before their children.
     */
    public function scopeOrdered($query)
    {
        $types = implode("','", array_keys(self::TYPE_ORDER));

        return $query
            ->orderByRaw("FIELD(type, '{$types}')")
            ->orderByRaw('CAST(SUBSTRING_INDEX(code, ".", 1) AS UNSIGNED)')
            ->orderByRaw("CASE WHEN code LIKE '%.%' THEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(code, '.', 2), '.', -1) AS UNSIGNED) ELSE 0 END")
            ->orderByRaw("CASE WHEN code LIKE '%.%.%' THEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(code, '.', 3), '.', -1) AS UNSIGNED) ELSE 0 END")
            ->orderBy('code');
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function canBePosted(): bool
    {
        return $this->is_postable && ! $this->hasChildren();
    }

    public static function sortByCodeNatural($collection)
    {
        return $collection->sort(function ($a, $b) {
            $aStr = (string) $a->code;
            $bStr = (string) $b->code;

            // PRIMARY: first digit groups categories (1=Asset, 2=Liability, 3=Equity, 4=Income, 5=Expense)
            $aFirst = $aStr[0] ?? '';
            $bFirst = $bStr[0] ?? '';
            if ($aFirst !== $bFirst) {
                return $aFirst <=> $bFirst;
            }

            // SECONDARY: natural numeric sort on dot-separated parts
            $aParts = explode('.', $aStr);
            $bParts = explode('.', $bStr);
            $maxLen = max(count($aParts), count($bParts));

            for ($i = 0; $i < $maxLen; $i++) {
                $aPart = $aParts[$i] ?? null;
                $bPart = $bParts[$i] ?? null;

                if ($aPart === null) {
                    return -1;
                }
                if ($bPart === null) {
                    return 1;
                }

                $aNum = is_numeric($aPart) ? (int) $aPart : 0;
                $bNum = is_numeric($bPart) ? (int) $bPart : 0;

                if ($aNum !== $bNum) {
                    return $aNum <=> $bNum;
                }
            }

            return 0;
        })->values();
    }
}
