<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One published final-result entry contributing to a CGPA.
 *
 * Snapshot values taken at CGPA computation time; never re-derived from
 * live marks. The unique(cumulative_result_id, final_result_id) constraint
 * prevents duplicate entries for the same published result.
 */
class AcademicCumulativeResultEntry extends Model
{
    protected $table = 'academic_cumulative_result_entries';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gpa' => 'float',
            'grade_points_earned' => 'float',
            'credits_earned' => 'float',
            'subjects_passed' => 'integer',
            'subjects_failed' => 'integer',
        ];
    }

    public function cumulativeResult(): BelongsTo
    {
        return $this->belongsTo(AcademicCumulativeResult::class, 'cumulative_result_id');
    }

    public function finalResult(): BelongsTo
    {
        return $this->belongsTo(AcademicFinalResult::class, 'final_result_id');
    }
}
