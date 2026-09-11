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
        'chief_complaints',
        'examination_findings',
        'advice',
        'follow_up_date',
        'is_finalized',
        'signed_at',
        'signed_by',
        'signature_hash',
    ];

    protected $casts = [
        'prescription_date' => 'date',
        'follow_up_date' => 'date',
        'is_finalized' => 'boolean',
        'signed_at' => 'datetime',
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
}
