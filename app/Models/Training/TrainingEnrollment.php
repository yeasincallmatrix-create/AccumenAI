<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingEnrollment extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_enrollments';

    protected $fillable = [
        'institute_id', 'batch_id', 'roll_no', 'trainee_id', 'student_id',
        'enrollment_date', 'status', 'payment_status',
    ];

    protected $casts = [
        'enrollment_date' => 'date', 'roll_no' => 'integer',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingBatch::class);
    }

    public function trainee(): BelongsTo
    {
        return $this->belongsTo(TrainingStudent::class, 'trainee_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(TrainingStudent::class, 'student_id');
    }
}
