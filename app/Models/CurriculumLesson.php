<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ordered lesson/topic inside a curriculum module (Step 42).
 *
 * Intentionally lightweight: no LMS, streaming or quiz engine — just the
 * ordered teaching structure with a content/material reference.
 */
class CurriculumLesson extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'curriculum_lessons';

    protected $guarded = [];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CurriculumModule::class, 'curriculum_module_id');
    }
}
