<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSubject extends Model
{
    protected $table = 'exam_subjects';

    public $timestamps = true;

    protected $guarded = [];

    public function components()
    {
        return $this->hasMany(ExamSubjectComponent::class, 'exam_subject_id')->orderBy('sort_order')->orderBy('id');
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withTrashed();
    }
}
