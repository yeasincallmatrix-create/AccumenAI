<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registered mobile device (FCM token) per institute user.
 */
class MobileDevice extends Model
{
    protected $fillable = [
        'institute_id',
        'user_id',
        'fcm_token',
        'platform',
        'device_name',
        'app_version',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
