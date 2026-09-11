<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 17 — Structured clinical follow-up (documentation only).
 *
 * "Clinical follow-up is planned" — NOT a scheduled visit. Follow-ups
 * never auto-book appointments and never duplicate the appointment
 * system; they are planning records with an explicit
 * planned→completed/cancelled lifecycle. Completed/cancelled rows persist;
 * there is no delete path and no reopening (terminal states are final).
 */
class FollowUp extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'medical_follow_ups';

    protected $fillable = [
        'institute_id',
        'branch_id',
        'patient_id',
        'encounter_id',
        'problem_id',
        'assigned_to',
        'created_by',
        'planned_date',
        'reason',
        'notes',
        'status',
        'completed_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected $casts = [
        'planned_date' => 'date',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function problem()
    {
        return $this->belongsTo(PatientProblem::class, 'problem_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopePlanned($query)
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    /**
     * Guarded terminal transition. Throws on illegal moves (including any
     * attempt to reopen completed/cancelled records).
     */
    public function transitionTo(string $status, ?string $reason = null): void
    {
        $allowed = [
            self::STATUS_PLANNED => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];

        if (! in_array($status, $allowed[$this->status] ?? [], true)) {
            throw new \RuntimeException(
                "Follow-up cannot move from {$this->status} to {$status}."
            );
        }

        $attributes = ['status' => $status];
        if ($status === self::STATUS_COMPLETED) {
            $attributes['completed_at'] = now();
        }
        if ($status === self::STATUS_CANCELLED) {
            $attributes['cancelled_at'] = now();
            $attributes['cancel_reason'] = $reason;
        }

        $this->update($attributes);
    }
}
