<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ordered module/subject inside a curriculum version (Step 42).
 *
 * Carries only the planned academic structure (expected marks, credit hours,
 * class count, duration, mandatory/optional, order). Actual marks and grading
 * remain the sole responsibility of the existing Assessment / Final Result
 * pipeline — nothing here feeds the grading engine.
 */
class CurriculumModule extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'curriculum_modules';

    protected $guarded = [];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'theory_marks' => 'decimal:2',
            'practical_marks' => 'decimal:2',
            'viva_marks' => 'decimal:2',
            'total_marks' => 'decimal:2',
            'credit_hours' => 'decimal:2',
            'class_count' => 'integer',
            'duration_hours' => 'decimal:2',
            'is_optional' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(CourseCurriculum::class, 'curriculum_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CurriculumLesson::class, 'curriculum_module_id')->orderBy('display_order')->orderBy('id');
    }
}
