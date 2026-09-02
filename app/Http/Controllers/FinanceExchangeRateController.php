<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\Accounting\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Exchange rate CRUD (STEP 19). Gated by fx.rates.manage.
 * Tenant/branch scoped; duplicate detection via service.
 */
class FinanceExchangeRateController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $rates = $this->exchangeRateService->list(
            $institute->id,
            $this->actingBranchId($request),
            $request->query(),
        );

        $currencies = Currency::query()->where('is_active', true)->orderBy('code')->get();

        return view('institute.finance.exchange-rates.index', [
            'institute' => $institute,
            'rates' => $rates,
            'currencies' => $currencies,
            'filters' => $request->query(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'from_currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'to_currency_id' => ['required', 'integer', 'exists:currencies,id', 'different:from_currency_id'],
            'rate' => ['required', 'numeric', 'min:0.000001'],
            'buy_rate' => ['nullable', 'numeric', 'min:0'],
            'sell_rate' => ['nullable', 'numeric', 'min:0'],
            'rate_date' => ['required', 'date'],
        ]);

        $this->exchangeRateService->create(
            $institute->id,
            $this->actingBranchId($request),
            array_merge($validated, ['source' => 'manual']),
            $this->actorId($request),
        );

        return redirect()->route('finance.exchange-rates.index')
            ->with('success', 'Exchange rate saved.');
    }

    public function update(Request $request, ExchangeRate $exchangeRate): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'rate' => ['sometimes', 'numeric', 'min:0.000001'],
            'buy_rate' => ['nullable', 'numeric', 'min:0'],
            'sell_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->exchangeRateService->update(
            $exchangeRate,
            $validated,
            $this->actorId($request),
        );

        return redirect()->route('finance.exchange-rates.index')
            ->with('success', 'Exchange rate updated.');
    }

    public function destroy(Request $request, ExchangeRate $exchangeRate): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->exchangeRateService->delete(
            $exchangeRate,
            $this->actorId($request),
        );

        return redirect()->route('finance.exchange-rates.index')
            ->with('success', 'Exchange rate deleted.');
    }
}
