<?php

namespace App\Http\Requests\Medical;

use App\Models\Medical\EncounterDiagnosis;
use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase 15 — Encounter diagnosis validation (documentation only).
 *
 * The clinician explicitly enters every field: label, classification
 * (diagnosis_type) and source. Nothing is inferred. Codes are accepted
 * only as clinician-supplied text; mapping_status resolution happens in
 * the controller against RECOGNIZED_CODE_SYSTEMS (empty until an
 * authoritative terminology source exists).
 *
 * Mutations on completed/cancelled encounters require an amendment
 * reason; open encounters accept changes directly.
 */
class DiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethodCacheable()) {
            return true;
        }

        try {
            $instituteId = MedicalScope::instituteIdOrFail();
        } catch (\Throwable) {
            return false;
        }

        $encounter = $this->route('encounter');
        if ($encounter && (int) $encounter->institute_id !== (int) $instituteId) {
            return false;
        }

        $diagnosis = $this->route('diagnosis');
        if ($diagnosis && (int) $diagnosis->institute_id !== (int) $instituteId) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        // Removal carries only a reason (required on closed encounters,
        // enforced in the controller); the diagnosis itself is route-bound.
        if ($this->route('diagnosis')) {
            return [
                'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
            ];
        }

        // Amendment reasons are enforced in the controller (required only
        // on closed encounters); here the field is merely accepted.
        return [
            'label' => ['required', 'string', 'max:255'],
            'diagnosis_type' => ['required', Rule::in(EncounterDiagnosis::TYPES)],
            'source' => ['sometimes', Rule::in([
                EncounterDiagnosis::SOURCE_STRUCTURED,
                EncounterDiagnosis::SOURCE_FREE_TEXT,
            ])],
            'code' => ['nullable', 'string', 'max:60'],
            'code_system' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
