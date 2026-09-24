<?php

namespace App\Models\Training;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingSchedule extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'training_schedules';

    protected $fillable = [
        'institute_id', 'batch_id', 'subject_id', 'day_of_week',
        'start_time', 'end_time', 'title', 'room',
        'effective_from', 'effective_to',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'start_time' => 'string',
        'end_time' => 'string',
        'effective_from' => 'string',
        'effective_to' => 'string',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingBatch::class, 'batch_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(TrainingSubject::class, 'subject_id');
    }

    /**
     * Effective-dated plan rows: a row belongs to the plan version that was in
     * effect on the given date (Y-m-d). NULL bounds are open-ended.
     */
    public function isActiveOn(string $date): bool
    {
        if ($this->effective_from !== null && $this->effective_from > $date) {
            return false;
        }
        if ($this->effective_to !== null && $this->effective_to < $date) {
            return false;
        }

        return true;
    }

    public function scopeActiveOn($query, string $date)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    public function scopeActiveBetween($query, string $from, string $to)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $to))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from));
    }

    /**
     * Start time as H:i (drops the seconds part coming from the time column).
     */
    public function getStartLabelAttribute(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function getEndLabelAttribute(): string
    {
        return substr((string) $this->end_time, 0, 5);
    }

    public function getStartMinutesAttribute(): int
    {
        [$h, $m] = array_pad(explode(':', substr((string) $this->start_time, 0, 5), 3), 2, 0);

        return ((int) $h) * 60 + (int) $m;
    }

    public function getEndMinutesAttribute(): int
    {
        [$h, $m] = array_pad(explode(':', substr((string) $this->end_time, 0, 5), 3), 2, 0);

        return ((int) $h) * 60 + (int) $m;
    }
}
