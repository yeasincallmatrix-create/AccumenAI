<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\User;

class PhysiotherapySession extends Model
{
    use SoftDeletes;

    protected $table = 'physiotherapy_sessions';

    protected $fillable = [
        'physiotherapy_plan_id', 'institute_id', 'branch_id', 'session_number', 'session_order',
        'therapist_id', 'session_date', 'duration_minutes',
        'pain_score_before', 'pain_score_after', 'assessment_notes',
        'treatment_given', 'exercises_done', 'equipment_used', 'progress_notes', 'next_session_focus',
        'status', 'attended_at', 'fee', 'payment_status',
    ];

    protected $casts = [
        'session_date' => 'date',
        'attended_at' => 'datetime',
        'session_order' => 'integer',
        'duration_minutes' => 'integer',
        'pain_score_before' => 'integer',
        'pain_score_after' => 'integer',
        'fee' => 'decimal:2',
    ];

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'attended' => 'Attended',
        'no_show' => 'No Show',
        'cancelled' => 'Cancelled',
    ];

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PhysiotherapyPlan::class, 'physiotherapy_plan_id');
    }

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function therapist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopeScheduled($q)
    {
        return $q->where('status', 'scheduled');
    }

    public function scopeAttended($q)
    {
        return $q->where('status', 'attended');
    }

    public function scopeToday($q)
    {
        return $q->whereDate('session_date', today());
    }

    public function painReduction(): ?int
    {
        if ($this->pain_score_before === null || $this->pain_score_after === null) {
            return null;
        }

        return $this->pain_score_before - $this->pain_score_after;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'scheduled' => 'info',
            'attended' => 'success',
            'no_show' => 'danger',
            'cancelled' => 'secondary',
            default => 'secondary',
        };
    }
}
