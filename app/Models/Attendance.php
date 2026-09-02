<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NOTE: This table does NOT have a 'subject_id' column.
 * Subject-specific attendance is currently stored in exam_results.attendance_marks.
 * If subject-level attendance is needed in future, add subject_id column and update relations.
 */
class Attendance extends Model
{
    use Concerns\TenantScoped;

    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_LEAVE = 'leave';

    public const STATUSES = [
        self::STATUS_PRESENT,
        self::STATUS_ABSENT,
        self::STATUS_LATE,
        self::STATUS_LEAVE,
    ];

    protected $table = 'attendance';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'class_date' => 'date',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'marked_by');
    }
}
