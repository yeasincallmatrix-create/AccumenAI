<?php

namespace App\Services\Medical;

use App\Models\Institute;
use App\Models\Medical\Admission;
use App\Models\Medical\NursingNote;
use App\Models\Medical\VitalSign;
use App\Support\MedicalScope;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Build the discharge-summary PDF for a discharged admission.
 *
 * Phase 2 adaptation: hospital identity resolves through MedicalScope +
 * Institute (the spec read `auth()->user()->institute`, which does not
 * exist for web-guard users).
 */
class DischargeSummaryService
{
    /**
     * Generate discharge summary PDF.
     */
    public function generatePdf(Admission $admission): \Barryvdh\DomPDF\PDF
    {
        $admission->load(['patient', 'admittingDoctor', 'dischargedBy', 'bed.ward']);

        // Get vitals and notes for the admission period.
        $vitals = VitalSign::where('admission_id', $admission->id)
            ->orderBy('recorded_at', 'desc')
            ->get();

        $notes = NursingNote::where('admission_id', $admission->id)
            ->orderBy('recorded_at', 'desc')
            ->get();

        $institute = Institute::find($admission->institute_id);

        $data = [
            'admission' => $admission,
            'patient' => $admission->patient,
            'doctor' => $admission->admittingDoctor,
            'vitals' => $vitals,
            'notes' => $notes,
            'discharge_date' => now()->format('d M Y'),
            'hospital_name' => $institute->name ?? 'Hospital',
            'hospital_address' => $institute->address ?? '',
        ];

        return Pdf::loadView('medical.admissions.discharge_summary', $data);
    }

    /**
     * Data for the on-screen discharge form (kept here so controller and
     * PDF share one source of related records).
     */
    public function formData(Admission $admission): array
    {
        $admission->load(['patient', 'bed.ward', 'admittingDoctor']);

        return [
            'admission' => $admission,
            'vitals' => VitalSign::where('admission_id', $admission->id)
                ->orderBy('recorded_at', 'desc')
                ->limit(10)
                ->get(),
            'notes' => NursingNote::where('admission_id', $admission->id)
                ->orderBy('recorded_at', 'desc')
                ->limit(10)
                ->get(),
        ];
    }
}
