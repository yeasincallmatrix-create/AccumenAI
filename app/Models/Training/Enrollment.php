<?php

namespace App\Models\Training;

use App\Models\Batch;
use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'enrollments';

    protected $guarded = [];

    protected $fillable = [
        'institute_id',
        'batch_id',
        'trainee_id',
        'student_id',
        'enrollment_date',
        'status',
        'payment_status',
        'roll_no',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'roll_no' => 'integer',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'trainee_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Course::class, 'course_id');
    }

    public function getRollNumberAttribute(): ?string
    {
        return $this->attributes['roll_no'] ?? null ? (string) $this->attributes['roll_no'] : null;
    }
}
