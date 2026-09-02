<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamSubjectComponent extends Model
{
    protected $table = 'exam_subject_components';

    protected $guarded = [];

    protected $casts = [
        'max_marks' => 'decimal:2',
        'weight' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function examSubject(): BelongsTo
    {
        return $this->belongsTo(ExamSubject::class, 'exam_subject_id');
    }
}
