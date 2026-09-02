<?php

namespace App\Http\Requests;

use App\Services\OfflineSyncService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfflineSyncRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1', 'max:100'],
            'records.*.client_uuid' => ['required', 'uuid', 'distinct'],
            'records.*.entity_type' => ['required', 'string', 'in:'.implode(',', OfflineSyncService::SUPPORTED_ENTITY_TYPES)],
            'records.*.created_offline_at' => ['required', 'date'],
            'records.*.payload' => ['required', 'array'],
            'records.*.payload.student_id' => ['nullable', 'integer'],
            'records.*.payload.amount' => ['required', 'numeric', 'gt:0'],
            'records.*.payload.description' => ['nullable', 'string', 'max:255'],
            'records.*.payload.payment_method' => ['nullable', 'in:cash,bkash,nagad,bank,other'],
            'records.*.payload.memo_number' => ['nullable', 'string', 'max:30'],
            'records.*.payload.created_at' => ['nullable', 'date'],
        ];
    }
}
