<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstituteFeatureOverride extends Model
{
    protected $table = 'institute_feature_overrides';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'feature_key',
        'enabled',
        'overridden_by',
        'reason',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'overridden_by');
    }
}
