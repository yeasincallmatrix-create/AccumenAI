<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Single prescription-item validation, shared by the draft add-item
 * endpoint. The full-form path validates through PrescriptionRequest's
 * items.* rules; both rule sets are intentionally identical.
 */
class PrescriptionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public static function itemRules(?int $instituteId = null): array
    {
        $instituteId ??= MedicalScope::instituteId();

        return [
            'medicine_id' => [
                'nullable',
                Rule::exists('medicines', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'medicine_name' => 'required|string|max:200',
            'dosage' => 'required|string|max:50',
            'frequency' => 'required|string|max:50',
            'duration_days' => 'nullable|integer|min:1',
            'quantity' => 'required|integer|min:1',
            'special_instructions' => 'nullable|string',
        ];
    }

    public function rules(): array
    {
        return self::itemRules();
    }

    public function messages(): array
    {
        return [
            'medicine_name.required' => 'Medicine name is required.',
            'dosage.required' => 'Dosage is required.',
            'frequency.required' => 'Frequency is required.',
            'quantity.min' => 'Quantity must be at least 1.',
        ];
    }
}
