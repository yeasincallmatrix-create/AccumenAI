<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OfflineSyncQueue extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'offline_sync_queue';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'created_offline_at' => 'datetime',
        'synced_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function materializedCashMemo(): HasOne
    {
        return $this->hasOne(CashMemo::class, 'offline_origin_id');
    }
}
