<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Chart of Accounts group — the report tree buckets
 * (asset / liability / equity / income / expense). Institute-scoped; branch_id
 * NULL = institute-wide group.
 */
class AccountGroup extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'account_groups';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'sort_order' => 'integer',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AccountGroup::class, 'parent_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'account_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }

    public static function hasGlobalRows(): bool
    {
        return true;
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
     *
     * Usage: AccountGroup::visibleTo($tenantId)->...
     * NOTE: scope name is 'institute' (per TenantScoped).
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

    /**
     * Canonical alias of scopeVisible().
     */
    public function scopeVisibleTo($query, int $instituteId)
    {
        return $this->scopeVisible($query, $instituteId);
    }
}
