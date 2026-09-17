<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalDocument extends Model
{
    use SoftDeletes;

    protected $table = 'medical_documents';

    public const DOCUMENT_TYPES = [
        'prescription' => 'Prescription',
        'lab_report' => 'Lab Report',
        'radiology_report' => 'Radiology Report',
        'discharge_summary' => 'Discharge Summary',
        'referral_letter' => 'Referral Letter',
        'consent_form' => 'Consent Form',
        'insurance_claim' => 'Insurance Claim',
        'vaccination_certificate' => 'Vaccination Certificate',
        'dental_record' => 'Dental Record',
        'physiotherapy_report' => 'Physiotherapy Report',
        'emergency_report' => 'Emergency Report',
        'admission_record' => 'Admission Record',
        'operative_note' => 'Operative Note',
        'pathology_report' => 'Pathology Report',
        'other' => 'Other',
    ];

    public const ACCESS_LEVELS = [
        'clinical' => 'Clinical Staff Only',
        'department' => 'Department Only',
        'patient' => 'Patient Visible',
    ];

    protected $fillable = [
        'institute_id', 'branch_id', 'patient_id',
        'document_number', 'document_type', 'title', 'description',
        'file_path', 'thumbnail_path', 'original_filename', 'mime_type',
        'file_size', 'file_hash', 'page_count',
        'source_type', 'source_id',
        'is_confidential', 'is_patient_visible', 'access_level',
        'tags', 'document_date', 'uploaded_by', 'verified_at', 'verified_by',
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
        'is_patient_visible' => 'boolean',
        'tags' => 'array',
        'document_date' => 'date',
        'verified_at' => 'datetime',
        'file_size' => 'integer',
        'page_count' => 'integer',
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

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function dischargeSummary()
    {
        return $this->hasOne(DischargeSummary::class, 'document_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('patient_id', $patientId);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('document_type', $type);
    }

    public function isConfidential(): bool
    {
        return (bool) $this->is_confidential;
    }

    public function canBeViewedBy($userId): bool
    {
        if (! $this->is_confidential) {
            return true;
        }

        return (int) $this->uploaded_by === (int) $userId
            || (int) ($this->verified_by ?? 0) === (int) $userId;
    }

    public function icon(): string
    {
        return match ($this->document_type) {
            'prescription' => 'bi-file-medical',
            'lab_report', 'pathology_report' => 'bi-eyedropper',
            'radiology_report' => 'bi-radioactive',
            'discharge_summary' => 'bi-box-arrow-right',
            'referral_letter' => 'bi-send',
            'consent_form' => 'bi-pen',
            'insurance_claim' => 'bi-receipt',
            'vaccination_certificate' => 'bi-shield-check',
            'dental_record' => 'bi-emoji-smile',
            'physiotherapy_report' => 'bi-activity',
            'emergency_report' => 'bi-heart-pulse',
            'admission_record' => 'bi-hospital',
            'operative_note' => 'bi-scissors',
            default => 'bi-file-earmark-text',
        };
    }

    public function documentTypeLabel(): string
    {
        return self::DOCUMENT_TYPES[$this->document_type] ?? $this->document_type;
    }
}
