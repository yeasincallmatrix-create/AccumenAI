<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Course extends Model
{
    use Concerns\DeletesFiles;

    protected $table = 'courses';

    protected $fileColumns = ['banner'];

    public $timestamps = true;

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
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function subCategory(): BelongsTo
    {
        return $this->belongsTo(CourseSubCategory::class, 'sub_category_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(\App\Models\Training\Enrollment::class);
    }

    public function courseRequests(): HasMany
    {
        return $this->hasMany(CourseRequest::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'course_subjects')->withTrashed();
    }

    public function instituteAssignments(): HasMany
    {
        return $this->hasMany(InstituteCourse::class, 'course_id');
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(CourseCurriculum::class);
    }

    public function activeCurriculum(): HasOne
    {
        return $this->hasOne(CourseCurriculum::class)
            ->where('status', CourseCurriculum::STATUS_ACTIVE)
            ->ofMany('version', 'max');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class);
    }

    public function getTitleAttribute(): ?string
    {
        return $this->attributes['name'] ?? null;
    }
}
