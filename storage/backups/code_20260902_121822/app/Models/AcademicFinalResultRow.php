<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per (placement, subject) graded-result snapshot of a locked/published final
 * result. Mirror of AcademicFinalResultService::subjectResult() output, written
 * once at LOCK and never edited afterwards.
 *
 * Reached exclusively through its tenant-scoped parent (AcademicFinalResult),
 * so it carries no institute_id.
 */
class AcademicFinalResultRow extends Model
{
    protected $table = 'academic_final_result_rows';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'aggregate' => 'float',
            'grade_point' => 'float',
            'credits' => 'float',
            'gpa_included' => 'boolean',
            'optional' => 'boolean',
        ];
    }

    public function result(): BelongsTo
    {
        return $this->belongsTo(AcademicFinalResult::class, 'result_id');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicPlacement::class, 'placement_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id')->withTrashed();
    }
}
