<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Course material / attachment (Step 42).
 *
 * Files are validated on upload (document/image whitelist, no executables)
 * and stored on the application's public disk — no second storage system.
 */
class CourseMaterial extends Model
{
    use Concerns\TenantScoped;

    protected $table = 'course_materials';

    protected $guarded = [];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CurriculumModule::class, 'curriculum_module_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'uploaded_by');
    }
}
