<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A selection group is a named set of optional/elective subjects for one
 * class/grade (optionally one group/stream) from which students select a
 * number of subjects (minimum_selection .. maximum_selection).
 *
 * Global shared reference data, country-scoped via the class — intentionally
 * NOT TenantScoped.
 */
class AcademicSelectionGroup extends Model
{
    protected $table = 'academic_selection_groups';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'minimum_selection' => 'integer',
            'maximum_selection' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function classGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class);
    }

    public function academicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class);
    }

    /**
     * Assignments that declare membership in this selection group.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SubjectAcademicAssignment::class, 'selection_group_id');
    }
}
