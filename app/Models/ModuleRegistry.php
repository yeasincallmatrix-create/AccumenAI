<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleRegistry extends Model
{
    protected $table = 'module_registry';

    public $timestamps = true;

    protected $fillable = [
        'key',
        'parent_key',
        'name',
        'type',
        'is_core',
        'description',
        'sort_order',
        'icon',
        'coming_soon',
        'index_route',
        'status',
    ];

    protected $casts = [
        'dependencies' => 'array',
        'is_core' => 'boolean',
    ];

    public function packageModules(): HasMany
    {
        return $this->hasMany(PackageModule::class, 'module_key', 'key');
    }
}
