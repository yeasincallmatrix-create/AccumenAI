<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencySettingRequest;
use App\Models\Currency;
use App\Models\CountryCurrencyMap;
use App\Models\ExchangeRate;
use App\Models\TenantCurrencySetting;
use App\Services\CurrencyService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CurrencySettingController extends Controller
{
    public function __construct(
        private readonly CurrencyService $currencyService
    ) {}

    public function index(Request $request): View
    {
        $instituteId = TenantContext::id();
        abort_unless($instituteId, 403);

        $setting = TenantCurrencySetting::forTenant($instituteId);
        $currencies = Currency::active()->orderBy('code')->get();
        $countryMap = CountryCurrencyMap::orderBy('country_name')->get();

        return view('settings.currency', [
            'setting' => $setting,
            'currencies' => $currencies,
            'countryMap' => $countryMap,
        ]);
    }

    public function update(CurrencySettingRequest $request): RedirectResponse
    {
        $instituteId = TenantContext::id();
        abort_unless($instituteId, 403);

        $data = $request->validated();
        $data['multi_currency_enabled'] = $request->boolean('multi_currency_enabled');

        // Ensure base_currency is always in available list
        if (! in_array($data['base_currency'], $data['available_currencies'] ?? [], true)) {
            $data['available_currencies'][] = $data['base_currency'];
        }
        $data['available_currencies'] = array_values(array_unique($data['available_currencies']));

        $this->currencyService->update($instituteId, $data);

        return redirect()
            ->route('settings.currency.index')
            ->with('status', 'Currency settings updated successfully.');
    }

    public function toggleMultiCurrency(Request $request): RedirectResponse
    {
        $instituteId = TenantContext::id();
        abort_unless($instituteId, 403);

        $request->validate([
            'multi_currency_enabled' => ['required', 'boolean'],
        ]);

        $enable = $request->boolean('multi_currency_enabled');

        if ($enable) {
            $additional = $request->input('available_currencies', []);
            $this->currencyService->enableMultiCurrency($instituteId, $additional);
        } else {
            $this->currencyService->disableMultiCurrency($instituteId);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'multi_currency_enabled' => $enable,
            ]);
        }

        return redirect()
            ->route('settings.currency.index')
            ->with('status', $enable ? 'Multi-currency enabled.' : 'Multi-currency disabled.');
    }

    public function getExchangeRates(Request $request): View
    {
        $instituteId = TenantContext::id();
        abort_unless($instituteId, 403);

        $setting = TenantCurrencySetting::forTenant($instituteId);
        abort_unless($setting->isMultiCurrencyEnabled(), 403);

        $rates = ExchangeRate::where('institute_id', $instituteId)
            ->with(['fromCurrency', 'toCurrency'])
            ->orderByDesc('rate_date')
            ->get();

        $currencies = Currency::active()->orderBy('code')->get();

        return view('settings.currency-rates', [
            'setting' => $setting,
            'rates' => $rates,
            'currencies' => $currencies,
        ]);
    }

    public function storeExchangeRate(Request $request): RedirectResponse
    {
        $instituteId = TenantContext::id();
        abort_unless($instituteId, 403);

        $setting = TenantCurrencySetting::forTenant($instituteId);
        abort_unless($setting->isMultiCurrencyEnabled(), 403);

        $data = $request->validate([
            'from_currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'to_currency' => ['required', 'string', 'size:3', 'exists:currencies,code', 'different:from_currency'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'rate_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $fromCurrency = Currency::where('code', $data['from_currency'])->first();
        $toCurrency = Currency::where('code', $data['to_currency'])->first();

        ExchangeRate::updateOrCreate(
            [
                'institute_id' => $instituteId,
                'from_currency_id' => $fromCurrency->id,
                'to_currency_id' => $toCurrency->id,
                'rate_date' => $data['rate_date'],
            ],
            [
                'rate' => $data['rate'],
                'created_by' => $request->user()?->id,
            ]
        );

        return redirect()
            ->route('settings.currency.rates')
            ->with('status', 'Exchange rate saved.');
    }

    public function destroyExchangeRate(ExchangeRate $rate): RedirectResponse
    {
        $instituteId = TenantContext::id();
        abort_unless($instituteId, 403);
        abort_unless($rate->institute_id === $instituteId, 403);

        $rate->delete();

        return redirect()
            ->route('settings.currency.rates')
            ->with('status', 'Exchange rate deleted.');
    }
}
