<?php

namespace App\Http\Requests\Medical;

use Illuminate\Foundation\Http\FormRequest;

class EmergencyVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $emergencyVisit = $this->route('emergencyVisit');

        return [
            'patient_id'            => 'nullable|exists:patients,id',
            'patient_name_temp'     => 'nullable|string|max:200',
            'patient_age'           => 'nullable|integer|min:0|max:150',
            'patient_gender'        => 'nullable|string|in:male,female,other',
            'patient_phone'         => 'nullable|string|max:20',
            'triage_level'          => 'nullable|string|in:red,orange,yellow,green,white',
            'arrival_mode'          => 'nullable|string|max:30',
            'arrival_reference'     => 'nullable|string|max:200',
            'arrived_at'            => 'nullable|date',
            'chief_complaint'       => 'nullable|string|max:2000',
            'history_notes'         => 'nullable|string',
            'vitals_snapshot'       => 'nullable|array',
            'vitals_snapshot.systolic_bp'    => 'nullable|numeric',
            'vitals_snapshot.diastolic_bp'   => 'nullable|numeric',
            'vitals_snapshot.heart_rate'     => 'nullable|integer',
            'vitals_snapshot.respiratory_rate' => 'nullable|integer',
            'vitals_snapshot.temperature'    => 'nullable|numeric',
            'vitals_snapshot.spo2'           => 'nullable|numeric|min:0|max:100',
            'vitals_snapshot.weight'         => 'nullable|numeric',
            'examination_findings'  => 'nullable|string',
            'provisional_diagnosis' => 'nullable|string|max:1000',
            'treatment_given'       => 'nullable|string',
            'attending_doctor_id'   => 'nullable|exists:users,id',
            'status'                => 'nullable|string|in:waiting,registered,triaged,attended,discharged,admitted,transferred,expired,left_without_treatment',
            'disposition'           => 'nullable|string|max:50',
            'disposition_notes'     => 'nullable|string',
            'triage_fee'            => 'nullable|numeric|min:0',
            'total_fee'             => 'nullable|numeric|min:0',
        ];
    }
}
