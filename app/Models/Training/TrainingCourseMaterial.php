<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use App\Models\InstituteUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCourseMaterial extends Model
{
    use TenantScoped;

    protected $table = 'training_course_materials';

    protected $guarded = [];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'uploaded_by');
    }
}
