<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 04 — Database-backed counter for clinical numbering.
 *
 * One row per (institute, sequence_type, year). Allocation happens under a
 * row lock inside NumberSequenceService; this model carries no logic beyond
 * the relations so the locking discipline stays in exactly one place.
 */
class NumberSequence extends Model
{
    public const TYPE_MR = 'mr';

    public const TYPE_PRESCRIPTION = 'prescription';

    public const TYPE_LAB_ORDER = 'lab_order';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_TPA_CLAIM = 'tpa_claim';

    public const TYPE_ENCOUNTER = 'encounter';

    public const TYPE_EMERGENCY = 'emergency';

    public const TYPE_RADIOLOGY = 'radiology';

    public const TYPE_BLOOD_DONOR = 'blood_donor';

    public const TYPE_BLOOD_UNIT = 'blood_unit';

    public const TYPE_BLOOD_REQUEST = 'blood_request';

    public const TYPE_PHYSIO_PLAN = 'physiotherapy_plan';

    public const TYPE_PHYSIO_SESSION = 'physiotherapy_session';

    public const TYPE_DENTAL_PROCEDURE = 'dental_procedure';

    public const TYPE_DENTAL_PLAN = 'dental_plan';

    public const TYPE_VACCINATION_RECORD = 'vaccination_record';

    public const TYPE_VACCINATION_CERTIFICATE = 'vaccination_certificate';

    public const TYPE_MEDICAL_DOCUMENT = 'medical_document';

    public const TYPE_DISCHARGE_SUMMARY = 'discharge_summary';

    public const TYPE_CLINICAL_NOTE = 'clinical_note';

    public const TYPE_DIET_PLAN = 'diet_plan';

    public const TYPE_AMBULANCE_DRIVER = 'ambulance_driver';

    public const TYPE_AMBULANCE_TRIP = 'ambulance_trip';

    protected $fillable = [
        'institute_id',
        'sequence_type',
        'year',
        'last_number',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_number' => 'integer',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }
}
