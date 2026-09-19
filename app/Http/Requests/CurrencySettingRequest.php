<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (method_exists($user, 'hasPermission')) {
            return $user->hasPermission('settings.manage');
        }

        return true;
    }

    public function rules(): array
    {
        $instituteId = \App\Support\TenantContext::id();

        return [
            'country_code' => ['nullable', 'string', 'size:2'],
            'base_currency' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
            'multi_currency_enabled' => ['sometimes', 'boolean'],
            'available_currencies' => ['required_if:multi_currency_enabled,true', 'array', 'min:1'],
            'available_currencies.*' => ['string', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
            'currency_position' => ['required', 'string', Rule::in(['before', 'after'])],
            'thousand_separator' => ['required', 'string', 'max:5'],
            'decimal_separator' => ['required', 'string', 'max:5'],
            'decimal_places' => ['required', 'integer', 'between:0,4'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->validated();

            // If disabling multi-currency, check for non-base transactions
            if (isset($data['multi_currency_enabled']) && ! $data['multi_currency_enabled']) {
                $setting = \App\Models\TenantCurrencySetting::forTenant(\App\Support\TenantContext::id());

                if ($setting->multi_currency_enabled && ! empty($setting->available_currencies)) {
                    $nonBaseCurrencies = array_diff(
                        $setting->available_currencies,
                        [$setting->base_currency]
                    );

                    if (! empty($nonBaseCurrencies)) {
                        // Check if any transactions use non-base currencies
                        $hasNonBaseTransactions = \App\Models\Invoice::query()
                            ->where('institute_id', \App\Support\TenantContext::id())
                            ->whereHas('currency', fn ($q) => $q->whereIn('code', $nonBaseCurrencies))
                            ->exists();

                        if ($hasNonBaseTransactions) {
                            $validator->errors()->add(
                                'multi_currency_enabled',
                                'Cannot disable multi-currency: there are transactions in non-base currencies. Existing transactions will remain, but no new non-base transactions can be created.'
                            );
                        }
                    }
                }
            }
        });
    }
}
