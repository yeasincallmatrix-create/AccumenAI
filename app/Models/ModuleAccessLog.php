<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleAccessLog extends Model
{
    protected $table = 'module_access_logs';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'module_key',
        'action',
        'actor_id',
        'actor_type',
        'previous_state',
        'new_state',
        'package_id',
        'notes',
        'reason',
        'feature_key',
        'decision',
        'request_id',
    ];

    public function getActorTypeLabelAttribute(): string
    {
        return $this->actor_type ?? 'legacy/unknown';
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class, 'package_id');
    }
}
