<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Institute customization of a group/stream. institute_id is scoped via
 * Concern\TenantScoped. Exactly one parent reference applies:
 *   - academic_group_id set           → override/disable of a global group
 *   - class_grade_id set              → custom group under a global class
 *   - institute_class_grade_id set    → custom group under a custom class
 */
class InstituteAcademicGroup extends Model
{
    use TenantScoped;

    protected $table = 'institute_academic_groups';

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

    public function academicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class);
    }

    public function classGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class);
    }

    public function instituteClassGrade(): BelongsTo
    {
        return $this->belongsTo(InstituteClassGrade::class);
    }
}
