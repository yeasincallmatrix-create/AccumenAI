<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DepositTdsRequest;
use App\Http\Requests\Settings\RecordTdsDeductionRequest;
use App\Models\TdsCertificate;
use App\Models\TdsDeduction;
use App\Services\Accounting\TdsService;

class TdsController extends Controller
{
    public function __construct(
        private TdsService $tdsService,
    ) {}

    public function index()
    {
        $instituteId = tenant_id();
        $country = tenant_country($instituteId);
        $currency = tenant_currency($instituteId);

        $deductions = TdsDeduction::where('institute_id', $instituteId)
            ->latest('deduction_date')
            ->paginate(20);

        $summary = $this->tdsService->getSummary($instituteId, (string) date('Y'));

        return view('settings.tds.index', compact('deductions', 'summary', 'country', 'currency'));
    }

    public function create()
    {
        $country = tenant_country();
        $currency = tenant_currency();

        return view('settings.tds.create', compact('country', 'currency'));
    }

    public function store(RecordTdsDeductionRequest $request)
    {
        $instituteId = tenant_id();
        $this->tdsService->record($instituteId, $request->validated());

        return redirect()->route('settings.tds.index')
            ->with('success', 'TDS deduction recorded successfully.');
    }

    public function deposit(TdsDeduction $tds, DepositTdsRequest $request)
    {
        $this->tdsService->markDeposited($tds, $request->validated());

        return redirect()->route('settings.tds.index')
            ->with('success', 'TDS deposit recorded successfully.');
    }

    public function certificates()
    {
        $instituteId = tenant_id();
        $country = tenant_country($instituteId);

        $certificates = TdsCertificate::where('institute_id', $instituteId)
            ->with('deduction')
            ->latest('issue_date')
            ->paginate(20);

        return view('settings.tds.certificates', compact('certificates', 'country'));
    }

    public function generateCertificate(TdsDeduction $tds)
    {
        $instituteId = tenant_id();
        $this->tdsService->generateCertificate($instituteId, $tds->id);

        return redirect()->route('settings.tds.certificates')
            ->with('success', 'TDS certificate generated successfully.');
    }
}
