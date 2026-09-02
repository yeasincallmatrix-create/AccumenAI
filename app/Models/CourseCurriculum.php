<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A curriculum version of a course for one institute (Step 42).
 *
 * Only one version may be active per course; activating a new version
 * archives the previous active one. Batches reference the exact version
 * they were created against so historical academic data is never rewritten.
 */
class CourseCurriculum extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'course_curricula';

    protected $guarded = [];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_ACTIVE, self::STATUS_ARCHIVED];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date:Y-m-d',
            'learning_objectives' => 'array',
            'total_duration_hours' => 'decimal:2',
            'total_classes' => 'integer',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(CurriculumModule::class, 'curriculum_id')->orderBy('display_order')->orderBy('id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class, 'course_id', 'course_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'curriculum_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
