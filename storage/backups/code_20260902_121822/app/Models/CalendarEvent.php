<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CalendarEvent extends Model
{
    use Concerns\TenantScoped, SoftDeletes;

    protected $table = 'calendar_events';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
        'is_all_day' => 'boolean',
        'recurrence_rule' => 'array',
        'meta' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (CalendarEvent $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function classGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class, 'class_grade_id');
    }

    public function academicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class, 'academic_group_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'teacher_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function parentEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'parent_event_id');
    }

    public function childEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'parent_event_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(CalendarEventReminder::class, 'event_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function scopeForDateRange($query, string $start, string $end)
    {
        return $query->where(function ($q) use ($start, $end) {
            $q->where('start_date', '>=', $start)
                ->where('start_date', '<=', $end);
        });
    }

    public function scopeForTeacher($query, int $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForBatch($query, int $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeForRoom($query, int $roomId)
    {
        return $query->where('room_id', $roomId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isRecurring(): bool
    {
        return ! empty($this->recurrence_rule);
    }

    public function isPast(): bool
    {
        return $this->start_date->isPast();
    }
}
