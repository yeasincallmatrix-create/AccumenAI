<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Institute customization of a class/grade. institute_id is scoped via
 * Concern\TenantScoped. Exactly one parent reference applies:
 *   - class_grade_id set               → override/disable of a global class
 *   - academic_level_id set            → custom class under a global level
 *   - institute_academic_level_id set  → custom class under a custom level
 */
class InstituteClassGrade extends Model
{
    use TenantScoped;

    protected $table = 'institute_class_grades';

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

    public function classGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class);
    }

    public function academicLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class);
    }

    public function instituteAcademicLevel(): BelongsTo
    {
        return $this->belongsTo(InstituteAcademicLevel::class);
    }
}
