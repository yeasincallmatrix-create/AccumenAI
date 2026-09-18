<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureRegistry extends Model
{
    protected $table = 'feature_registry';

    protected $fillable = [
        'feature_key',
        'module_key',
        'name',
        'description',
        'parent_feature_key',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
