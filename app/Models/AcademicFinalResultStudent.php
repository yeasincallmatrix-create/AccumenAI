<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-student GPA snapshot of a locked/published final result.
 *
 * Only written by the lifecycle lock step; reached exclusively through its
 * tenant-scoped parent (AcademicFinalResult), so it carries no institute_id.
 */
class AcademicFinalResultStudent extends Model
{
    protected $table = 'academic_final_result_students';

    public $timestamps = true;

    protected $guarded = [];

    public const GPA_COMPUTED = 'computed';

    public const GPA_UNAVAILABLE = 'unavailable';

    protected function casts(): array
    {
        return [
            'gpa' => 'float',
            'passed_count' => 'integer',
            'failed_count' => 'integer',
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
}
