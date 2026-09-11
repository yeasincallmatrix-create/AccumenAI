<?php

namespace App\Http\Requests\Medical;

use App\Models\Medical\PatientProblem;
use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase 17 — Longitudinal problem validation (documentation only).
 *
 * Store requires an explicit clinician-entered label and problem type;
 * nothing is inferred. Source links are optional and ownership-checked in
 * the controller (same institute + same patient + diagnosis-in-encounter).
 * Member actions (inactivate/reactivate/resolve/link/unlink) carry only
 * reason/date fields; the problem itself is route-bound.
 */
class ProblemRequest extends FormRequest
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

        $patient = $this->route('patient');
        if ($patient && (int) $patient->institute_id !== (int) $instituteId) {
            return false;
        }

        $problem = $this->route('problem');
        if ($problem && (int) $problem->institute_id !== (int) $instituteId) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        // Member actions: only reason/date payloads.
        if ($this->route('problem')) {
            return [
                'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'resolved_date' => ['sometimes', 'nullable', 'date'],
                'encounter_id' => ['sometimes', 'nullable', 'integer'],
                'encounter_diagnosis_id' => ['sometimes', 'nullable', 'integer'],
            ];
        }

        return [
            'label' => ['required', 'string', 'max:255'],
            'problem_type' => ['required', Rule::in(PatientProblem::TYPES)],
            'source' => ['sometimes', Rule::in(['structured', 'free_text'])],
            'code' => ['nullable', 'string', 'max:60'],
            'code_system' => ['nullable', 'string', 'max:60'],
            'onset_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'encounter_id' => ['sometimes', 'nullable', 'integer'],
            'encounter_diagnosis_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
