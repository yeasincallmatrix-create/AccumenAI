<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Global, country-scoped subject assignment for a class/grade (optionally
 * narrowed to a group/stream). Shared reference data — intentionally NOT
 * TenantScoped (the same curriculum applies across institutes).
 */
class SubjectAcademicAssignment extends Model
{
    protected $table = 'subject_academic_assignments';

    public $timestamps = true;

    protected $guarded = [];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class);
    }

    public function academicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class);
    }

    public function selectionGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicSelectionGroup::class, 'selection_group_id');
    }
}
