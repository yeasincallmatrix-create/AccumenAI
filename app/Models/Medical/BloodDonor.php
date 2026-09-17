<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;

class BloodDonor extends Model
{
    use SoftDeletes;

    protected $table = 'blood_donors';

    protected $fillable = [
        'institute_id', 'branch_id', 'donor_number',
        'first_name', 'last_name', 'phone', 'email', 'date_of_birth', 'gender',
        'blood_group', 'weight_kg', 'hemoglobin',
        'medical_history', 'is_eligible', 'last_donation_date',
        'status', 'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'weight_kg' => 'decimal:1',
        'hemoglobin' => 'decimal:1',
        'is_eligible' => 'boolean',
        'last_donation_date' => 'date',
    ];

    public const BLOOD_GROUPS = [
        'A+' => 'A+',
        'A-' => 'A-',
        'B+' => 'B+',
        'B-' => 'B-',
        'AB+' => 'AB+',
        'AB-' => 'AB-',
        'O+' => 'O+',
        'O-' => 'O-',
    ];

    public const GENDERS = [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'blocked' => 'Blocked',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function bloodUnits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BloodUnit::class, 'donor_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeBloodGroup($q, string $group)
    {
        return $q->where('blood_group', $group);
    }

    public function scopeEligible($q)
    {
        return $q->where('is_eligible', true);
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function bloodGroupLabel(): string
    {
        return self::BLOOD_GROUPS[$this->blood_group] ?? $this->blood_group;
    }

    public function genderLabel(): string
    {
        return self::GENDERS[$this->gender] ?? $this->gender;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'inactive' => 'secondary',
            'blocked' => 'danger',
            default => 'secondary',
        };
    }

    public function isEligibleForDonation(): bool
    {
        if ($this->status !== 'active' || !$this->is_eligible) {
            return false;
        }

        if ($this->weight_kg < 50) {
            return false;
        }

        $minHemoglobin = $this->gender === 'female' ? 12.0 : 12.5;
        if ($this->hemoglobin < $minHemoglobin) {
            return false;
        }

        if ($this->last_donation_date && $this->last_donation_date->diffInDays(now()) < 90) {
            return false;
        }

        return true;
    }
}
