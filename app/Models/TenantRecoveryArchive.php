<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TenantRecoveryArchive extends Model
{
    protected $table = 'tenant_recovery_archives';

    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'archived_at' => 'datetime',
    ];

    public function archivable(): MorphTo
    {
        return $this->morphTo();
    }
}
