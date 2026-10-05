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
        $type = (string) $this->input('type', '');

        return [
            'code' => [
                'required',
                'string',
                'regex:/^\d{1,4}(\.\d+){0,3}$/',
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
            'account_group_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($instituteId): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $visible = \App\Models\AccountGroup::withoutGlobalScopes()
                        ->where('id', (int) $value)
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($instituteId) {
                            $q->where(function ($g) {
                                $g->whereNull('institute_id')->where('is_system', 1);
                            })->orWhere('institute_id', $instituteId);
                        })
                        ->exists();

                    if (! $visible) {
                        $fail('The selected account group does not belong to this institute.');
                    }
                },
            ],
            'parent_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($instituteId, $type): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $parent = ChartOfAccount::withoutGlobalScopes()
                        ->where('id', (int) $value)
                        ->whereNull('deleted_at')
                        ->where(function ($q) use ($instituteId) {
                            $q->where(function ($g) {
                                $g->whereNull('institute_id')->where('is_system', 1);
                            })->orWhere('institute_id', $instituteId);
                        })
                        ->first();

                    if ($parent === null) {
                        $fail('The parent account does not belong to this institute.');

                        return;
                    }

                    if ($type !== '' && $parent->type !== $type) {
                        $fail('The parent account must be of the same type.');
                    }
                },
            ],
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
            'code.regex' => 'Code must be 1-4 digits optionally followed by dot-separated subcodes (e.g., 1, 1000, 1000.1, 1000.1.1). Hyphen is NOT allowed — use dots.',
            'code.unique' => 'This code conflicts with an existing global or tenant account.',
        ];
    }
}
