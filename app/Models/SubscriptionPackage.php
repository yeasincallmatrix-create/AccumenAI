<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends Model
{
    protected $table = 'subscription_packages';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'slug',
        'price_monthly',
        'price_yearly',
        'max_students',
        'max_teachers',
        'max_courses',
        'max_branches',
        'storage_limit_mb',
        'sms_limit_monthly',
        'is_default',
        'status',
    ];

    public function institutes(): HasMany
    {
        return $this->hasMany(Institute::class);
    }

    public function packageModules(): HasMany
    {
        return $this->hasMany(PackageModule::class, 'package_id');
    }

    public function enabledModuleKeys(): array
    {
        return $this->packageModules()->where('enabled', true)->pluck('module_key')->toArray();
    }
}
