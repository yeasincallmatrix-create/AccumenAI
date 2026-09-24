<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingCourse extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_courses';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'requirements' => 'array',
            'outcomes' => 'array',
            'prerequisites' => 'array',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
            'is_test' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TrainingCourseCategory::class, 'category_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(TrainingCourseSubCategory::class, 'sub_category_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(TrainingSubject::class, 'training_course_subjects', 'course_id', 'subject_id')->withPivot('assigned_by')->withTimestamps();
    }

    public function materials(): HasMany
    {
        return $this->hasMany(TrainingCourseMaterial::class, 'course_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(TrainingBatch::class, 'course_id');
    }

    public function getTitleAttribute(): ?string
    {
        return $this->attributes['name'] ?? null;
    }
}
