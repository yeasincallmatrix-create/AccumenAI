<?php

namespace App\Http\Requests\Medical;

use App\Models\Medical\LabOrder;
use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    protected function prepareForValidation(): void
    {
        // The route carries {order} (bound LabOrder); it is canonical, so
        // the body does not need to repeat it.
        $order = $this->route('order');

        if ($order instanceof LabOrder) {
            $this->merge(['order_id' => $order->id]);
        }
    }

    public function rules(): array
    {
        $instituteId = MedicalScope::instituteId();

        return [
            'order_id' => [
                'required',
                Rule::exists('lab_orders', 'id')->where(
                    fn ($q) => $q->where('institute_id', $instituteId)
                ),
            ],
            'results' => 'required|array|min:1',
            'results.*.result_value' => 'nullable|string|max:255',
            'results.*.result_text' => 'nullable|string',
            'results.*.comments' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Order is required.',
            'order_id.exists' => 'Selected order does not exist.',
            'results.required' => 'At least one result is required.',
        ];
    }
}
