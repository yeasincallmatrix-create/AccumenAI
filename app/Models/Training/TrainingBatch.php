<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use App\Models\Training\TrainingEnrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingBatch extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_batches';

    protected $fillable = [
        'is_test', 'institute_id', 'course_id', 'curriculum_id', 'academic_year_id',
        'branch_id', 'teacher_id', 'room_id', 'name', 'batch_code', 'shift',
        'start_date', 'end_date', 'seat_capacity', 'seat_filled', 'status',
        'attendance_threshold',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'is_test' => 'boolean',
        'seat_capacity' => 'integer', 'seat_filled' => 'integer',
        'attendance_threshold' => 'decimal:2',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(TrainingEnrollment::class, 'batch_id');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(TrainingAttendance::class, 'batch_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(TrainingExam::class, 'batch_id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(TrainingCertificate::class, 'batch_id');
    }

    /**
     * Loose coupling to shared Course (education-owned).
     * Column: training_batches.course_id (nullable, no FK enforced).
     * Data coupling only — does NOT grant education module access to training.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Course::class, 'course_id');
    }
}
