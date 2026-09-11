<?php

namespace App\Http\Requests\Medical;

use App\Support\MedicalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase 18.1 — branch administration validation.
 *
 * Branches belong to exactly one institute (never reassigned between
 * institutes). Deletion is not offered; lifecycle is active/inactive.
 */
class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->isMethodCacheable()) {
            return true;
        }

        try {
            MedicalScope::instituteIdOrFail();
        } catch (\Throwable) {
            return false;
        }

        $branch = $this->route('branch');
        if ($branch && (int) $branch->institute_id !== (int) MedicalScope::instituteIdOrFail()) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'manager_user_id' => ['nullable', 'integer'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}
