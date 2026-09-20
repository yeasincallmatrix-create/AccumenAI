<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ChartOfAccount::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $instituteId = tenant_id();

        return [
            'code' => [
                'required',
                'string',
                'regex:/^\d{4}(-\d{2})?$/',
                Rule::unique('chart_of_accounts', 'code')
                    ->where(function ($q) use ($instituteId) {
                        $q->where('institute_id', $instituteId)
                            ->orWhere(function ($g) {
                                $g->whereNull('institute_id')
                                    ->where('is_system', 1);
                            });
                    }),
            ],
            'name' => 'required|string|max:150',
            'type' => 'required|in:asset,liability,equity,income,expense',
            'account_group_id' => ['nullable', 'integer', 'exists:account_groups,id'],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'is_cash' => ['nullable', 'boolean'],
            'is_bank' => ['nullable', 'boolean'],
            'is_receivable' => ['nullable', 'boolean'],
            'is_payable' => ['nullable', 'boolean'],
            'cash_flow_category' => ['nullable', Rule::in(['operating', 'investing', 'financing'])],
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Code must be 4 digits (e.g., 1001) or 4-2 sub (e.g., 6600-01).',
            'code.unique' => 'This code conflicts with an existing global or tenant account.',
        ];
    }
}
