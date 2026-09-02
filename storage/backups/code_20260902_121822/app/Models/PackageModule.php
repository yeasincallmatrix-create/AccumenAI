<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageModule extends Model
{
    protected $table = 'package_modules';

    public $timestamps = true;

    protected $guarded = [];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleRegistry::class, 'module_key', 'key');
    }
}
