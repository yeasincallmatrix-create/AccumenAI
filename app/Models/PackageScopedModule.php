<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageScopedModule extends Model
{
    protected $table = 'package_scoped_modules';

    protected $fillable = [
        'package_scope_id',
        'module_key',
        'enabled',
        'scope_hash',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $row) {
            if ($row->scope_hash === null && $row->package_scope_id && $row->module_key) {
                $row->scope_hash = $row->package_scope_id . '|' . $row->module_key;
            }
        });
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(PackageScope::class, 'package_scope_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleRegistry::class, 'module_key', 'key');
    }
}
