<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingStudent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingAttendance extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_attendance';

    protected $fillable = [
        'institute_id', 'batch_id', 'student_id', 'class_date', 'status', 'remarks', 'marked_by',
    ];

    protected $casts = [
        'class_date' => 'date',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingBatch::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(TrainingStudent::class);
    }
}
