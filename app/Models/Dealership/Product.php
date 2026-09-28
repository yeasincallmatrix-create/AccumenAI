<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use TenantScoped;

    protected $table = 'dealership_products';

    protected $guarded = [];

    protected $casts = [
        'retail_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
}
