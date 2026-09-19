<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Services\ModuleAccessService;

class PackageScope extends Model
{
    protected $table = 'package_scopes';

    public $timestamps = true;

    protected $fillable = [
        'package_id',
        'country_id',
        'industry_id',
        'sub_industry_id',
        'inherit_from_parent',
        'price_monthly',
        'price_yearly',
        'currency',
        'status',
    ];

    protected $casts = [
        'inherit_from_parent' => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (PackageScope $scope) {
            $scope->scope_hash = implode('-', [
                $scope->package_id,
                $scope->country_id ?? 'G',
                $scope->industry_id ?? 'G',
                $scope->sub_industry_id ?? 'G',
            ]);
        });

        static::updated(function (PackageScope $scope) {
            app(ModuleAccessService::class)->flushFeatureCacheForScope($scope);
        });
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }

    public function subIndustry(): BelongsTo
    {
        return $this->belongsTo(SubIndustry::class, 'sub_industry_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopedModules(): HasMany
    {
        return $this->hasMany(PackageScopedModule::class, 'package_scope_id');
    }

    public function isGlobal(): bool
    {
        return is_null($this->country_id)
            && is_null($this->industry_id)
            && is_null($this->sub_industry_id);
    }

    /**
     * Get the effective monthly price for this scope.
     * If scope has no price, walk up parent chain.
     * Falls back to the package's base price.
     */
    public function effectiveMonthlyPrice(): ?float
    {
        if ($this->price_monthly !== null) {
            return (float) $this->price_monthly;
        }

        $parent = app(ModuleAccessService::class)
            ->resolveParentScope($this);

        if ($parent) {
            return $parent->effectiveMonthlyPrice();
        }

        return $this->package?->price_monthly !== null
            ? (float) $this->package->price_monthly
            : null;
    }

    /**
     * Get the effective yearly price for this scope.
     * If scope has no price, walk up parent chain.
     * Falls back to the package's base price.
     */
    public function effectiveYearlyPrice(): ?float
    {
        if ($this->price_yearly !== null) {
            return (float) $this->price_yearly;
        }

        $parent = app(ModuleAccessService::class)
            ->resolveParentScope($this);

        if ($parent) {
            return $parent->effectiveYearlyPrice();
        }

        return $this->package?->price_yearly !== null
            ? (float) $this->package->price_yearly
            : null;
    }

    /**
     * Get the effective currency for this scope.
     * If scope has no currency, walk up parent chain.
     * Falls back to 'BDT' as default.
     */
    public function effectiveCurrency(): ?string
    {
        if ($this->currency !== null) {
            return $this->currency;
        }

        $parent = app(ModuleAccessService::class)
            ->resolveParentScope($this);

        if ($parent) {
            return $parent->effectiveCurrency();
        }

        return 'BDT';
    }

    /**
     * Get a formatted price string for this scope.
     */
    public function effectivePriceString(): string
    {
        $currency = $this->effectiveCurrency() ?? 'BDT';
        $monthly = $this->effectiveMonthlyPrice();
        $yearly = $this->effectiveYearlyPrice();

        $parts = [];
        if ($monthly !== null) {
            $parts[] = number_format($monthly, 2) . "/month ({$currency})";
        }
        if ($yearly !== null) {
            $parts[] = number_format($yearly, 2) . "/year ({$currency})";
        }

        return implode(' | ', $parts) ?: 'No pricing set';
    }
}
