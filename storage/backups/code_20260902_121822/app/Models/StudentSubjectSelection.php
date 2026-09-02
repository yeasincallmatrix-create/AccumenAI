<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A subject a student selected for one academic placement. Only selected
 * subjects are stored (mandatory are auto-included at save time). The row
 * references the real Subject Master and snapshots the requirement source at
 * selection time.
 */
class StudentSubjectSelection extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'student_subject_selections';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_selected' => 'boolean',
            'is_mandatory' => 'boolean',
        ];
    }

    public function academicPlacement(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicPlacement::class, 'academic_placement_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }

    public function selectionGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicSelectionGroup::class);
    }
}
