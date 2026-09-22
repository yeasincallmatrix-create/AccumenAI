<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingClass extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_classes';

    protected $fillable = [
        'country_id', 'education_system_id', 'academic_level_id', 'name', 'code',
        'sequence', 'display_order', 'status', 'metadata',
    ];

    protected $casts = [
        'sequence' => 'integer', 'display_order' => 'integer', 'metadata' => 'array',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
