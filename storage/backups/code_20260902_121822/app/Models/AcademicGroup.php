<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Optional stream/grouping under a class grade (Science / Humanities /
 * Business Studies / Arts / General ...). Classes may have no groups.
 *
 * Global shared reference data — intentionally NOT TenantScoped.
 */
class AcademicGroup extends Model
{
    protected $table = 'academic_groups';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function educationSystem(): BelongsTo
    {
        return $this->belongsTo(EducationSystem::class);
    }

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }

    public function classGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class);
    }

    public function academicAssignments(): HasMany
    {
        return $this->hasMany(SubjectAcademicAssignment::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_academic_assignments', 'academic_group_id', 'subject_id')
            ->withPivot(['display_order', 'status']);
    }
}
