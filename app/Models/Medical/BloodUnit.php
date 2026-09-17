<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\User;

class BloodUnit extends Model
{
    use SoftDeletes;

    protected $table = 'blood_units';

    protected $fillable = [
        'institute_id', 'branch_id', 'unit_number',
        'donor_id', 'blood_group', 'component', 'volume_ml',
        'collection_date', 'expiry_date', 'status',
        'crossmatch_required', 'screening_hiv', 'screening_hbsag', 'screening_hcv', 'screening_syphilis', 'screening_malaria',
        'current_patient_id', 'reserved_at', 'notes',
    ];

    protected $casts = [
        'collection_date' => 'datetime',
        'expiry_date' => 'datetime',
        'reserved_at' => 'datetime',
        'volume_ml' => 'integer',
        'crossmatch_required' => 'boolean',
    ];

    public const COMPONENTS = [
        'Whole Blood' => 'Whole Blood',
        'PRBC' => 'Packed Red Blood Cells',
        'FFP' => 'Fresh Frozen Plasma',
        'Platelets' => 'Platelets',
        'Cryoprecipitate' => 'Cryoprecipitate',
        'Granulocytes' => 'Granulocytes',
    ];

    public const STATUSES = [
        'available' => 'Available',
        'reserved' => 'Reserved',
        'issued' => 'Issued',
        'expired' => 'Expired',
        'discarded' => 'Discarded',
    ];

    public const SCREENING_STATUSES = [
        'pending' => 'Pending',
        'pass' => 'Pass',
        'fail' => 'Fail',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function donor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BloodDonor::class, 'donor_id');
    }

    public function currentPatient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class, 'current_patient_id');
    }

    public function issuedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function issueItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BloodIssueItem::class, 'blood_unit_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopeAvailable($q)
    {
        return $q->where('status', 'available');
    }

    public function scopeExpired($q)
    {
        return $q->where('expiry_date', '<', now());
    }

    public function scopeByBloodGroup($q, string $group)
    {
        return $q->where('blood_group', $group);
    }

    public function scopeByComponent($q, string $component)
    {
        return $q->where('component', $component);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available' && !$this->isExpired();
    }

    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    public function componentLabel(): string
    {
        return self::COMPONENTS[$this->component] ?? $this->component;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'available' => 'success',
            'reserved' => 'warning',
            'issued' => 'info',
            'expired' => 'danger',
            'discarded' => 'secondary',
            default => 'secondary',
        };
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }

        $days = (int) now()->diffInDays($this->expiry_date, false);

        return max(0, $days);
    }
}
