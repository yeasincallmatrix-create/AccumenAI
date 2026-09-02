<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $notification) {
            static::pruneExcess($notification);
        });
    }

    /**
     * Keep at most 100 notifications per bucket.
     * Bucket key: (scope, institute_id, target_user_type, target_user_id).
     * Global platform notifications are bucketed by scope alone.
     */
    public static function pruneExcess(self $notification): void
    {
        $query = static::query()
            ->where('scope', $notification->scope);

        if ($notification->scope === 'institute') {
            $query->where('institute_id', $notification->institute_id);
        } elseif ($notification->scope === 'user') {
            $query->where('target_user_type', $notification->target_user_type)
                ->where('target_user_id', $notification->target_user_id);
        } elseif ($notification->scope === 'platform') {
            // platform is global — no extra filter
        } else {
            // Fallback: global cap
            $query = static::query();
        }

        $count = $query->count();

        if ($count > 100) {
            $excess = $count - 100;
            $idsToDelete = (clone $query)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($excess)
                ->pluck('id');

            if ($idsToDelete->isNotEmpty()) {
                static::whereIn('id', $idsToDelete)->delete();
            }
        }
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }
}
