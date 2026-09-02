<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cumulative GPA (CGPA) record: one per institute + student + academic level.
 *
 * Tracks the running CGPA across all published final results that contribute
 * to it. Computed from frozen snapshot data, never from mutable live marks.
 *
 * At most one active CGPA record exists per (institute, student, academic_level)
 * triple. The CGPA is recomputed whenever a new published result is added or
 * an existing entry is updated.
 */
class AcademicCumulativeResult extends Model
{
    use TenantScoped;

    protected $table = 'academic_cumulative_results';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'cumulative_gpa' => 'float',
            'total_grade_points' => 'float',
            'total_credits' => 'float',
            'periods_completed' => 'integer',
            'status' => 'string',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }

    /**
     * All published final-result entries contributing to this CGPA.
     */
    public function entries(): HasMany
    {
        return $this->hasMany(AcademicCumulativeResultEntry::class, 'cumulative_result_id');
    }

    /**
     * Resolve the CGPA record for a student in a given academic level context.
     * Returns null when no record exists yet (the caller creates one on demand).
     */
    public function scopeForContext(Builder $query, int $studentId, ?int $academicLevelId = null): Builder
    {
        return $query->where('student_id', $studentId)
            ->where('academic_level_id', $academicLevelId);
    }
}
