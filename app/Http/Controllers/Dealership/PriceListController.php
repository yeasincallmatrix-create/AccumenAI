<?php

namespace App\Http\Controllers\Dealership;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Dealership\PriceList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PriceListController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $date = $request->input('date');

        $prices = PriceList::query()
            ->when($date, fn ($q) => $q
                ->where(fn ($w) => $w->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))
                ->where(fn ($w) => $w->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date)))
            ->where('is_active', true)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('dealership.price_lists.index', [
            'institute' => $institute,
            'prices' => $prices,
            'date' => $date,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireInstitute($request);

        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'channel' => ['nullable', 'in:general,retail,wholesale,sub_dealer'],
            'price' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        PriceList::create([
            'brand_id' => $data['brand_id'],
            'product_id' => $data['product_id'] ?? null,
            'channel' => $data['channel'] ?? 'general',
            'price' => $data['price'],
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return redirect()->route('dealership.price_lists.index')->with('success', 'Price added.');
    }
}
