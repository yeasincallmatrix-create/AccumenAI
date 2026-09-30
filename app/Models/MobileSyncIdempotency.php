<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * client_id → server_id mapping for idempotent mobile push.
 * Generic (no FKs): entity names the target table family.
 */
class MobileSyncIdempotency extends Model
{
    public $timestamps = false;

    protected $table = 'mobile_sync_idempotency';

    protected $fillable = [
        'institute_id',
        'client_id',
        'entity',
        'server_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
