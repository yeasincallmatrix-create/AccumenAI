<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuleRegistry extends Model
{
    protected $table = 'module_registry';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'dependencies' => 'array',
    ];

    public function packageModules(): HasMany
    {
        return $this->hasMany(PackageModule::class, 'module_key', 'key');
    }
}
