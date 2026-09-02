<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingBatchResult extends Model
{
    protected $table = 'training_batch_results';

    protected $fillable = ['institute_id', 'batch_id', 'student_id', 'total_marks', 'obtained_marks', 'percentage', 'status', 'published_at'];

    protected $casts = [
        'published_at' => 'datetime',
        'total_marks' => 'decimal:2',
        'obtained_marks' => 'decimal:2',
        'percentage' => 'decimal:2',
    ];

    public function batch(): BelongsTo { return $this->belongsTo(Batch::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    // Legacy alias for backwards compatibility
    public function trainee(): BelongsTo { return $this->belongsTo(Student::class, 'student_id'); }
    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }

    public function getResultStatusAttribute(): ?string
    {
        return $this->attributes['status'] ?? null;
    }
}
