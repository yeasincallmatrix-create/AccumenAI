<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ComputeCorporateTaxRequest;
use App\Http\Requests\Settings\RecordAdvanceTaxRequest;
use App\Models\AdvanceTaxPayment;
use App\Models\CorporateTaxComputation;
use App\Services\Accounting\CorporateTaxService;

class CorporateTaxController extends Controller
{
    public function __construct(
        private CorporateTaxService $corporateTaxService,
    ) {}

    public function index()
    {
        $instituteId = tenant_id();
        $country = tenant_country($instituteId);
        $currency = tenant_currency($instituteId);

        $computations = CorporateTaxComputation::where('institute_id', $instituteId)
            ->latest('financial_year')
            ->paginate(20);

        $advancePayments = AdvanceTaxPayment::where('institute_id', $instituteId)
            ->latest('due_date')
            ->paginate(20);

        return view('settings.tax.corporate.index', compact('computations', 'advancePayments', 'country', 'currency'));
    }

    public function compute(ComputeCorporateTaxRequest $request)
    {
        $instituteId = tenant_id();
        $this->corporateTaxService->compute(
            $instituteId,
            $request->validated('financial_year'),
            $request->validated()
        );

        return redirect()->route('settings.corporate-tax.index')
            ->with('success', 'Corporate tax computed successfully.');
    }

    public function recordAdvance(RecordAdvanceTaxRequest $request)
    {
        $instituteId = tenant_id();
        $this->corporateTaxService->recordAdvancePayment($instituteId, $request->validated());

        return redirect()->route('settings.corporate-tax.index')
            ->with('success', 'Advance tax payment recorded successfully.');
    }
}
