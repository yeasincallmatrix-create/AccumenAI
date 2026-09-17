<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class ClinicalNote extends Model
{
    use SoftDeletes;

    protected $table = 'clinical_notes';

    public const NOTE_TYPES = [
        'progress' => 'Progress Note',
        'consultation' => 'Consultation',
        'nursing' => 'Nursing Note',
        'procedure' => 'Procedure Note',
        'operative' => 'Operative Note',
        'referral' => 'Referral Note',
        'discharge' => 'Discharge Note',
        'telephone' => 'Telephone Note',
        'counseling' => 'Counseling Note',
    ];

    protected $fillable = [
        'institute_id', 'branch_id', 'note_number',
        'patient_id', 'author_id', 'note_type',
        'encounter_type', 'encounter_id',
        'subjective', 'objective', 'assessment', 'plan',
        'content', 'addendum',
        'noted_at', 'is_signed', 'signed_at', 'signature_hash',
        'is_amended', 'amendment_reason',
    ];

    protected $casts = [
        'noted_at' => 'datetime',
        'signed_at' => 'datetime',
        'is_signed' => 'boolean',
        'is_amended' => 'boolean',
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

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('clinical_notes.institute_id', $id);
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('clinical_notes.patient_id', $patientId);
    }

    public function scopeOfType($q, string $type)
    {
        return $q->where('clinical_notes.note_type', $type);
    }

    public function scopeSigned($q)
    {
        return $q->where('clinical_notes.is_signed', true);
    }

    public function isSigned(): bool
    {
        return (bool) $this->is_signed;
    }

    public function canBeAmended(): bool
    {
        return $this->is_signed && ! $this->is_amended;
    }

    public function sign($userId): void
    {
        $this->update([
            'is_signed' => true,
            'signed_at' => now(),
            'signature_hash' => hash('sha256', $this->id . '|' . $userId . '|' . now()->toIso8601String()),
        ]);
    }

    public function amend($reason, ?string $addendum = null): void
    {
        $this->update([
            'is_amended' => true,
            'amendment_reason' => $reason,
            'addendum' => $addendum ?? $this->addendum,
        ]);
    }

    public function noteTypeLabel(): string
    {
        return self::NOTE_TYPES[$this->note_type] ?? $this->note_type;
    }
}
