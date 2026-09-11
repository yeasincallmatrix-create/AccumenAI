<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 17 — Follow-up validation (planning record only).
 *
 * Creation requires patient (route), planned date and reason. Links to
 * encounter/problem/assignee are optional and ownership-checked in the
 * controller. Member actions (complete/cancel) carry only reason/notes.
 * Follow-ups never book appointments.
 */
class FollowUpRequest extends FormRequest
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

        $followup = $this->route('followup');
        if ($followup && (int) $followup->institute_id !== (int) $instituteId) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        if ($this->route('followup')) {
            return [
                'reason' => ['sometimes', 'nullable', 'string', 'max:2000'],
                'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            ];
        }

        return [
            'planned_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'encounter_id' => ['sometimes', 'nullable', 'integer'],
            'problem_id' => ['sometimes', 'nullable', 'integer'],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
