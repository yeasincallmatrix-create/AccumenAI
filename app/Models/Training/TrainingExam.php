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
        'pass_marks', 'written_percent', 'practical_percent', 'viva_percent', 'weight_percent', 'status', 'created_by', 'published_at',
    ];

    protected $casts = [
        'exam_date' => 'date', 'full_marks' => 'decimal:2', 'pass_marks' => 'decimal:2',
        'written_percent' => 'decimal:2', 'practical_percent' => 'decimal:2', 'viva_percent' => 'decimal:2',
        'weight_percent' => 'decimal:2',
        'is_test' => 'boolean', 'published_at' => 'datetime',
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

    /**
     * Student-centric summary: one pass/fail decision per student.
     * Subject rows: average of subject totals vs pass_marks (same as Overall view).
     * Fallback: overall row (subject_id null). Enrolled with no marks counts as fail.
     */
    public function studentResultSummary(): array
    {
        $results = $this->relationLoaded('results')
            ? $this->results
            : $this->results()->get(['id', 'exam_id', 'student_id', 'subject_id', 'marks_obtained', 'result_status']);

        $resultsByStudent = $results->groupBy('student_id');
        $enrolledIds = $this->batch?->enrollments?->pluck('student_id')->all() ?? [];
        $studentIds = array_values(array_unique(array_merge(
            $enrolledIds,
            $resultsByStudent->keys()->all(),
        )));

        $passMarks = (float) ($this->pass_marks ?? 0);
        $passCount = 0;
        $failCount = 0;

        foreach ($studentIds as $studentId) {
            $studentResults = $resultsByStudent->get($studentId, collect());
            if ($studentResults->isEmpty()) {
                $failCount++;
                continue;
            }

            $subjectResults = $studentResults->filter(fn ($r) => $r->subject_id !== null);
            if ($subjectResults->isNotEmpty()) {
                $average = (float) $subjectResults->avg('marks_obtained');
                $passed = $average >= $passMarks;
            } else {
                $overall = $studentResults->firstWhere('subject_id', null);
                $passed = $overall !== null && $overall->result_status === 'pass';
            }

            if ($passed) {
                $passCount++;
            } else {
                $failCount++;
            }
        }

        return [
            'students' => count($studentIds),
            'pass' => $passCount,
            'fail' => $failCount,
        ];
    }
}
