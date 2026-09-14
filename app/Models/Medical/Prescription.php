<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $table = 'prescriptions';

    protected $fillable = [
        'institute_id',
        'branch_id',
        'patient_id',
        'doctor_id',
        'encounter_id',
        'prescription_number',
        'prescription_date',
        'diagnosis',
        'investigations',
        'chief_complaints',
        'examination_findings',
        'advice',
        'follow_up_date',
        'is_finalized',
        'signed_at',
        'signed_by',
        'signature_hash',
        'version',
        'parent_prescription_id',
        'amendment_reason',
        'amended_by',
        'amended_at',
    ];

    protected $casts = [
        'prescription_date' => 'date',
        'follow_up_date' => 'date',
        'is_finalized' => 'boolean',
        'signed_at' => 'datetime',
        'amended_at' => 'datetime',
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

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function items()
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(PrescriptionAuditLog::class);
    }

    public function parent()
    {
        return $this->belongsTo(Prescription::class, 'parent_prescription_id');
    }

    public function amendments()
    {
        return $this->hasMany(Prescription::class, 'parent_prescription_id');
    }

    public function amendedByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'amended_by');
    }

    public function latestAmendment()
    {
        return $this->hasOne(Prescription::class, 'parent_prescription_id')
            ->orderByDesc('version');
    }

    /**
     * Phase 13 — CDS findings for this prescription (open history plus
     * acknowledged/overridden/resolved trail; ordered newest first).
     */
    public function cdsFindings()
    {
        return $this->hasMany(CdsFinding::class, 'prescription_id')->orderByDesc('id');
    }

    /**
     * Display status mapped from the finalized flag + signing metadata.
     */
    public function statusLabel(): string
    {
        if ($this->is_finalized) {
            return 'signed';
        }

        return 'draft';
    }

    public function labOrders()
    {
        return $this->hasMany(LabOrder::class);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('prescription_date', today());
    }

    /**
     * Phase 3 addition.
     *
     * Whether every item on this prescription has been dispensed.
     */
    public function isFullyDispensed(): bool
    {
        return $this->items()->where('status', '!=', 'dispensed')->count() === 0;
    }

    /**
     * Phase 3 addition.
     */
    public function getPendingItemsCountAttribute(): int
    {
        return $this->items()->where('status', 'pending')->count();
    }

    /**
     * Phase 3 addition.
     */
    public function getDispensedItemsCountAttribute(): int
    {
        return $this->items()->where('status', 'dispensed')->count();
    }

    public function isAmendment(): bool
    {
        return $this->parent_prescription_id !== null;
    }

    public function isAmended(): bool
    {
        return $this->amendments()->exists();
    }

    public function isLatestVersion(): bool
    {
        return ! $this->isAmended();
    }

    /**
     * Find today's prescription for a doctor-patient pair in an institute.
     * Returns the LATEST version (original or amendment).
     */
    public static function todayForDoctorPatient(
        int $doctorId,
        int $patientId,
        int $instituteId
    ): ?self {
        return static::where('doctor_id', $doctorId)
            ->where('patient_id', $patientId)
            ->where('institute_id', $instituteId)
            ->whereDate('prescription_date', now()->toDateString())
            ->orderByDesc('version')
            ->first();
    }
}
