<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

class ApiEndpoint extends Model
{
    use TenantScoped;

    protected $table = 'dealership_api_endpoints';

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
