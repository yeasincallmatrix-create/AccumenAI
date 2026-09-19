<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function isGlobal(): bool
    {
        return is_null($this->country_id)
            && is_null($this->industry_id)
            && is_null($this->sub_industry_id);
    }
}
