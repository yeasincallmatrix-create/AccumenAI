<?php

namespace App\Http\Requests\Medical;

use App\Models\LabIntegration\LabAnalyzer;
use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LabAnalyzerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && MedicalScope::instituteId() !== null;
    }

    public function rules(): array
    {
        $analyzer = $this->route('analyzer');
        $analyzerId = $analyzer?->id;
        $instituteId = MedicalScope::instituteId();

        // Code is immutable after creation (readonly in the edit form):
        // required + unique on store, ignored on update.
        $codeRule = $analyzerId
            ? ['sometimes', 'string', 'max:50']
            : [
                'required', 'string', 'max:50',
                Rule::unique('lab_analyzers', 'code')
                    ->where('institute_id', $instituteId)
                    ->whereNull('deleted_at'),
            ];

        return [
            'code' => $codeRule,
            'name' => 'required|string|max:200',
            'manufacturer' => 'nullable|string|max:200',
            'model' => 'nullable|string|max:100',
            'serial_no' => 'nullable|string|max:100',
            'instrument_type' => ['required', Rule::in(LabAnalyzer::INSTRUMENT_TYPES)],
            'protocol' => ['required', Rule::in(LabAnalyzer::PROTOCOLS)],
            'adapter_key' => 'required|string|max:100',
            'adapter_version' => 'nullable|string|max:20',
            'connection_type' => ['required', Rule::in(LabAnalyzer::CONNECTION_TYPES)],
            'host' => 'nullable|string|max:100',
            'port' => 'nullable|integer|min:1|max:65535',
            'serial_port' => 'nullable|string|max:50',
            'baud_rate' => 'nullable|integer|in:9600,19200,38400,57600,115200',
            'parity' => 'nullable|in:none,even,odd',
            'stop_bits' => 'nullable|integer|in:1,2',
            'data_bits' => 'nullable|integer|in:7,8',
            'capabilities' => 'nullable|array',
            'capabilities.result_upload' => 'boolean',
            'capabilities.worklist' => 'boolean',
            'capabilities.query' => 'boolean',
            'capabilities.bidirectional' => 'boolean',
            'is_enabled' => 'boolean',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'capabilities' => [
                'result_upload' => $this->boolean('capabilities.result_upload'),
                'worklist' => $this->boolean('capabilities.worklist'),
                'query' => $this->boolean('capabilities.query'),
                'bidirectional' => $this->boolean('capabilities.bidirectional'),
            ],
            'is_enabled' => $this->boolean('is_enabled'),
        ]);
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This analyzer code is already used in your institute.',
        ];
    }
}
