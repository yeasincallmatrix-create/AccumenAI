<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Institute customization of an academic level.
 *
 * institute_id is scoped via Concern\TenantScoped as with all institute-owned
 * tables. Row semantics:
 *   - academic_level_id set  → override/disable of a global level
 *   - academic_level_id null → institute-created custom level (is_custom = 1)
 */
class InstituteAcademicLevel extends Model
{
    use TenantScoped;

    protected $table = 'institute_academic_levels';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /** The global level this row overrides (null when institute-created). */
    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }

    public function educationSystem(): BelongsTo
    {
        return $this->belongsTo(EducationSystem::class);
    }
}
