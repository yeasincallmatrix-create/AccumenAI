<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesForce extends Model
{
    use TenantScoped;

    protected $table = 'dealership_sales_force';

    protected $guarded = [];

    protected $casts = [
        'monthly_target' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function beat(): BelongsTo
    {
        return $this->belongsTo(Beat::class, 'beat_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SrOrder::class, 'sales_force_id');
    }
}
