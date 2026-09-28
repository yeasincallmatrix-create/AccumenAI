<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Beat extends Model
{
    use TenantScoped;

    protected $table = 'dealership_beats';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function salesForce(): HasMany
    {
        return $this->hasMany(SalesForce::class, 'beat_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'beat_id');
    }
}
