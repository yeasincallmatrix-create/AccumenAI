<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingCourseSubject extends Model
{
    protected $table = 'training_course_subjects';

    public $timestamps = false;

    protected $guarded = [];

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(TrainingSubject::class, 'subject_id');
    }
}
