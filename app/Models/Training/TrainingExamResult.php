<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use App\Models\Training\TrainingExam;
use App\Models\Training\TrainingStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingExamResult extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_exam_results';

    protected $fillable = [
        'institute_id', 'exam_id', 'student_id', 'subject_id', 'marks_obtained',
        'written_marks', 'practical_marks', 'viva_marks', 'other_marks', 'component_marks',
        'attendance_marks', 'grade', 'gpa', 'result_status', 'remarks', 'entered_by',
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2', 'written_marks' => 'decimal:2',
        'practical_marks' => 'decimal:2', 'viva_marks' => 'decimal:2',
        'other_marks' => 'decimal:2', 'component_marks' => 'decimal:2',
        'attendance_marks' => 'decimal:2', 'gpa' => 'decimal:2',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(TrainingExam::class, 'exam_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(TrainingStudent::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(TrainingSubject::class, 'subject_id');
    }
}
