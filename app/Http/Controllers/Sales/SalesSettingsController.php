<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Sales\SalesNumberingService;
use App\Services\Sales\SalesSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesSettingsController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly SalesSettingsService $settings,
        private readonly SalesNumberingService $numbering,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $config = $this->settings->get($institute->id);

        // Previews for numbering (do not increment)
        $previews = [
            'quotation' => $this->numbering->preview($institute->id, null, 'quotation'),
            'sales_order' => $this->numbering->preview($institute->id, null, 'sales_order'),
            'delivery' => $this->numbering->preview($institute->id, null, 'delivery'),
        ];

        return view('sales.settings', [
            'institute' => $institute,
            'config' => $config,
            'previews' => $previews,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'quotation_enabled' => ['nullable', 'boolean'],
            'sales_order_enabled' => ['nullable', 'boolean'],
            'delivery_enabled' => ['nullable', 'boolean'],
            'invoice_integration' => ['nullable', 'boolean'],
            'default_currency' => ['nullable', 'string', 'max:10'],
            'default_payment_terms' => ['nullable', 'string', 'max:50'],
            'default_tax_behavior' => ['nullable', 'in:exclusive,inclusive,none'],
            'default_discount_behavior' => ['nullable', 'in:per_line,per_total,none'],
            'numbering' => ['nullable', 'array'],
            'numbering.quotation.prefix' => ['nullable', 'string', 'max:20'],
            'numbering.quotation.padding' => ['nullable', 'integer', 'min:3', 'max:10'],
            'numbering.sales_order.prefix' => ['nullable', 'string', 'max:20'],
            'numbering.sales_order.padding' => ['nullable', 'integer', 'min:3', 'max:10'],
            'numbering.delivery.prefix' => ['nullable', 'string', 'max:20'],
            'numbering.delivery.padding' => ['nullable', 'integer', 'min:3', 'max:10'],
        ]);

        // Normalize booleans (checkboxes)
        foreach (['enabled', 'quotation_enabled', 'sales_order_enabled', 'delivery_enabled', 'invoice_integration'] as $k) {
            $data[$k] = $request->boolean($k);
        }

        $this->settings->update($institute->id, $data, $this->actorId($request));

        // Sync numbering sequences if prefix/padding changed
        if (isset($data['numbering'])) {
            foreach (['quotation', 'sales_order', 'delivery'] as $type) {
                if (isset($data['numbering'][$type])) {
                    $prefix = $data['numbering'][$type]['prefix'] ?? SalesSettingsService::DEFAULTS['numbering'][$type]['prefix'];
                    $padding = (int) ($data['numbering'][$type]['padding'] ?? SalesSettingsService::DEFAULTS['numbering'][$type]['padding']);
                    $this->numbering->configure($institute->id, null, $type, $prefix, $padding, $this->actorId($request));
                }
            }
        }

        return back()->with('status', 'Sales settings updated.');
    }
}
