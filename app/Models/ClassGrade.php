<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A generic academic unit under an academic level. Stores a generic concept;
 * the displayed terminology (Class / Grade / Year / Form / Level) comes from
 * the country's academic_unit_label configuration, never hard-coded here.
 *
 * Global shared reference data — intentionally NOT TenantScoped.
 *
 * C3 — Structure Versioning / Archive: soft-deletes preserve historical
 * placements. Hard-deletion is blocked when referenced by student placements,
 * assessment subjects, or aggregation schemes so that published snapshots
 * remain readable. Deactivating via status=false should be used for
 * operational archiving; soft-delete is the versioned archive path.
 */
class ClassGrade extends Model
{
    use SoftDeletes;

    protected $table = 'class_grades';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (ClassGrade $grade) {
            // Block any deletion (soft or force) when historical data exists.
            // This keeps placement snapshots and final-result rows readable.
            $hasPlacements = \App\Models\StudentAcademicPlacement::withTrashed()
                ->where('class_grade_id', $grade->id)
                ->exists();

            // Also check with snapshot fallback (placements that were snapshotted before soft-delete column existed)
            $hasAssignments = \App\Models\SubjectAcademicAssignment::where('class_grade_id', $grade->id)->exists();
            $hasAggregation = \Illuminate\Support\Facades\DB::table('academic_result_aggregation_schemes')
                ->where('class_grade_id', $grade->id)
                ->exists();
            $hasAssessments = \App\Models\AcademicAssessment::where('class_grade_id', $grade->id)->exists();

            if ($hasPlacements || $hasAssignments || $hasAggregation || $hasAssessments) {
                // Allow status=false deactivation, but block actual row removal.
                // Throwing ValidationException surfaces as 422 in controllers/services.
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'class_grade' => 'Cannot archive/delete this class/grade because it is referenced by placements, assignments, assessments or aggregation schemes. Deactivate it (status=false) or migrate placements first; historical structure must remain readable.',
                ]);
            }
        });
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

    public function groups(): HasMany
    {
        return $this->hasMany(AcademicGroup::class)->orderBy('display_order')->orderBy('id');
    }

    public function academicAssignments(): HasMany
    {
        return $this->hasMany(SubjectAcademicAssignment::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'subject_academic_assignments', 'class_grade_id', 'subject_id')
            ->withPivot(['display_order', 'status']);
    }
}
