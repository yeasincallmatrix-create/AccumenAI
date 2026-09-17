<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\Medical\Patient;
use App\Models\User;

class BloodRequest extends Model
{
    use SoftDeletes;

    protected $table = 'blood_requests';

    protected $fillable = [
        'institute_id', 'branch_id', 'request_number',
        'patient_id', 'requested_by', 'doctor_id',
        'blood_group', 'component', 'units_requested', 'units_issued',
        'urgency', 'status', 'clinical_indication', 'diagnosis',
        'approved_by', 'approved_at', 'fulfilled_at',
        'cancelled_at', 'cancel_reason', 'notes',
    ];

    protected $casts = [
        'units_requested' => 'integer',
        'units_issued' => 'integer',
        'approved_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const URGENCY_LEVELS = [
        'routine' => 'Routine',
        'urgent' => 'Urgent',
        'emergent' => 'Emergent',
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'partially_fulfilled' => 'Partially Fulfilled',
        'fulfilled' => 'Fulfilled',
        'cancelled' => 'Cancelled',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function requestedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function doctor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function approvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function issueItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BloodIssueItem::class, 'blood_request_id');
    }

    public function issuedUnits(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(BloodUnit::class, 'blood_issue_items', 'blood_request_id', 'blood_unit_id')
            ->withPivot(['issued_by', 'issued_at', 'returned_at', 'return_reason', 'status'])
            ->withTimestamps();
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    public function scopeFulfilled($q)
    {
        return $q->where('status', 'fulfilled');
    }

    public function scopeUrgent($q)
    {
        return $q->where('urgency', '!=', 'routine');
    }

    public function scopeByBloodGroup($q, string $group)
    {
        return $q->where('blood_group', $group);
    }

    public function scopeToday($q)
    {
        return $q->whereDate('created_at', today());
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isFulfilled(): bool
    {
        return $this->status === 'fulfilled';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isFullyFulfilled(): bool
    {
        return $this->units_issued >= $this->units_requested;
    }

    public function remainingUnits(): int
    {
        return max(0, $this->units_requested - $this->units_issued);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'partially_fulfilled' => 'primary',
            'fulfilled' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    public function urgencyLabel(): string
    {
        return self::URGENCY_LEVELS[$this->urgency] ?? $this->urgency;
    }

    public function urgencyColor(): string
    {
        return match ($this->urgency) {
            'routine' => 'secondary',
            'urgent' => 'warning',
            'emergent' => 'danger',
            default => 'secondary',
        };
    }
}
