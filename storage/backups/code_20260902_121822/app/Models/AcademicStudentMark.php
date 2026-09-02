<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One current academic marks record per (student, assessment component).
 *
 * Context columns (assessment / subject / component / placement) mean the row
 * always knows exactly what exam, subject, component and placement it belongs
 * to. obtained_mark is derived-config independent: the component row it links
 * to carries the authoritative full/pass marks.
 */
class AcademicStudentMark extends Model
{
    use TenantScoped;

    protected $table = 'academic_student_marks';

    public $timestamps = true;

    protected $guarded = [];

    public const STATUS_ENTERED = 'entered';

    public const STATUS_ABSENT = 'absent';

    protected function casts(): array
    {
        return [
            'obtained_mark' => 'float',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(AcademicAssessment::class, 'academic_assessment_id');
    }

    public function assessmentSubject(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubject::class, 'assessment_subject_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubjectComponent::class, 'assessment_component_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicPlacement::class, 'academic_placement_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'entered_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }
}
