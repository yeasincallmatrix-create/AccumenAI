<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingExam extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_exams';

    protected $fillable = [
        'institute_id', 'course_id', 'batch_id', 'title', 'exam_date', 'full_marks',
        'pass_marks', 'written_percent', 'practical_percent', 'viva_percent', 'status', 'created_by',
    ];

    protected $casts = [
        'exam_date' => 'date', 'full_marks' => 'decimal:2', 'pass_marks' => 'decimal:2',
        'written_percent' => 'decimal:2', 'practical_percent' => 'decimal:2', 'viva_percent' => 'decimal:2',
        'is_test' => 'boolean',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(TrainingExamResult::class, 'exam_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingBatch::class, 'batch_id');
    }

    /**
     * Loose coupling to shared Course (education-owned).
     * Column: training_exams.course_id (nullable, no FK enforced).
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Course::class, 'course_id');
    }

    /**
     * No training exam-subjects pivot exists.
     * Returns the linked course's subjects (empty when course_id is null).
     * Accessor (not a real relation) — do not eager-load via with('subjects').
     */
    public function getSubjectsAttribute()
    {
        return $this->course?->subjects ?? collect();
    }
}
