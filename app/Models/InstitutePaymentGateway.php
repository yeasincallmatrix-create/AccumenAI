<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstitutePaymentGateway extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'institute_payment_gateways';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'gateway_id');
    }
}
