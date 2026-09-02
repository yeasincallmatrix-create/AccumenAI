<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    protected $table = 'payment_gateways';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'config_schema' => 'array',
        'is_active' => 'boolean',
    ];

    public function instituteGateways(): HasMany
    {
        return $this->hasMany(InstitutePaymentGateway::class, 'gateway_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(OnlinePaymentAttempt::class, 'gateway_id');
    }
}
