<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();

        $bed = $this->route('bed');
        $bedId = $bed instanceof \App\Models\Medical\Bed ? $bed->id : null;

        // Ward-scoped bed_number uniqueness (matches the DB unique key).
        $wardId = $this->input('ward_id', $bed?->ward_id);

        return [
            'ward_id' => [
                'required',
                Rule::exists('wards', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'bed_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('beds', 'bed_number')
                    ->ignore($bedId)
                    ->where(fn ($q) => $q->where('ward_id', $wardId)),
            ],
            'status' => ['required', Rule::in(['available', 'occupied', 'reserved', 'maintenance'])],
            'notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'ward_id.required' => 'Please select a ward.',
            'ward_id.exists' => 'Selected ward does not exist.',
            'bed_number.required' => 'Bed number is required.',
            'bed_number.unique' => 'This bed number already exists in the selected ward.',
            'status.required' => 'Bed status is required.',
            'status.in' => 'Invalid bed status selected.',
        ];
    }
}
