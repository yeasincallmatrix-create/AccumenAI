<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reusable notification template (STEP 45).
 *
 * institute_id NULL = an industry-neutral default template that any institute
 * may use; institute_id set = an institute-specific override. Both can coexist
 * because the resolver prefers the institute template and falls back to the
 * global default.
 */
class NotificationTemplate extends Model
{
    public const CHANNELS = ['in_app', 'email', 'sms'];

    protected $table = 'notification_templates';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
