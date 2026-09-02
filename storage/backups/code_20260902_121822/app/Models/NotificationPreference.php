<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-recipient notification preference (STEP 45).
 *
 * A preference row disables (enabled = false) a channel for a recipient, either
 * for every event (event = NULL) or for one specific event. Absence of a row
 * means "use the default channel routing".
 */
class NotificationPreference extends Model
{
    protected $table = 'notification_preferences';

    public $timestamps = true;

    protected $guarded = [];
}
