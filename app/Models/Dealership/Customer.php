<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use TenantScoped;

    protected $table = 'dealership_customers';

    protected $guarded = [];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function beat(): BelongsTo
    {
        return $this->belongsTo(Beat::class, 'beat_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SrOrder::class, 'customer_id');
    }

    public function creditLimit(): BelongsTo
    {
        return $this->belongsTo(CreditLimit::class, 'customer_id', 'customer_id');
    }
}
