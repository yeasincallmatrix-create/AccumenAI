<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageScopedFeature extends Model
{
    protected $table = 'package_scoped_features';

    public $timestamps = true;

    protected $fillable = [
        'package_scope_id',
        'feature_key',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function scope(): BelongsTo
    {
        return $this->belongsTo(PackageScope::class, 'package_scope_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(FeatureRegistry::class, 'feature_key', 'feature_key');
    }
}
