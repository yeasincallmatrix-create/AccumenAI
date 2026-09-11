<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        $medicine = $this->route('medicine');
        $medicineId = $medicine instanceof \App\Models\Medical\Medicine ? $medicine->id : null;

        return [
            // NOTE: `medicines.code` is globally unique in the Phase 0
            // schema, so the rule is global too (not institute-scoped).
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('medicines', 'code')->ignore($medicineId),
            ],
            'generic_name' => 'required|string|max:150',
            'brand_name' => 'nullable|string|max:150',
            'category' => 'nullable|string|max:100',
            'dosage_form' => 'required|string|max:50',
            'strength' => 'nullable|string|max:50',
            'unit' => 'required|string|max:20',
            'pack_size' => 'required|integer|min:1',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'vat_percentage' => 'nullable|numeric|min:0|max:100',
            'reorder_level' => 'required|integer|min:0',
            'reorder_quantity' => 'required|integer|min:0',
            'requires_prescription' => 'nullable|boolean',
            'is_controlled' => 'nullable|boolean',
            'dgda_code' => 'nullable|string|max:100',
            'dgda_dar_number' => 'nullable|string|max:100',
            'dgda_concept_id' => 'nullable|string|max:100',
            'dgda_status' => 'nullable|string|max:50',
            'side_effects' => 'nullable|string',
            'contraindications' => 'nullable|string',
            'storage_conditions' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Medicine code is required.',
            'code.unique' => 'This medicine code already exists.',
            'generic_name.required' => 'Generic name is required.',
            'dosage_form.required' => 'Dosage form is required.',
            'unit.required' => 'Unit is required.',
            'pack_size.min' => 'Pack size must be at least 1.',
            'selling_price.min' => 'Selling price must be 0 or greater.',
            'purchase_price.min' => 'Purchase price must be 0 or greater.',
            'reorder_level.min' => 'Reorder level must be 0 or greater.',
        ];
    }
}
