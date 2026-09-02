<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstituteModuleEntitlement extends Model
{
    use SoftDeletes;

    protected $table = 'institute_module_entitlements';

    protected $guarded = [];

    protected $casts = [
        'is_grant' => 'boolean',
        'auto_renew' => 'boolean',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_starts_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleRegistry::class, 'module_key', 'key');
    }

    public function purchasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'granted_by');
    }
}
