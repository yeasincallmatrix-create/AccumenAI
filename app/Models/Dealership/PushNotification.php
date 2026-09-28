<?php

namespace App\Models\Dealership;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushNotification extends Model
{
    use TenantScoped;

    protected $table = 'dealership_push_notifications';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function salesForce(): BelongsTo
    {
        return $this->belongsTo(SalesForce::class, 'recipient_sales_force_id');
    }

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }
}
