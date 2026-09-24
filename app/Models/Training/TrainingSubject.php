<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingSubject extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_subjects';

    protected $fillable = [
        'institute_id', 'category_id', 'subject_type', 'subject_code', 'name', 'slug',
        'short_name', 'description', 'status',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TrainingCourseCategory::class, 'category_id');
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(TrainingCourse::class, 'training_course_subjects', 'subject_id', 'course_id')->withPivot('assigned_by')->withTimestamps();
    }
}
