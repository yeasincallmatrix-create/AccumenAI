<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Accounting\TaxReportService;

class TaxReportController extends Controller
{
    public function __construct(
        private TaxReportService $taxReportService,
    ) {}

    public function tdsSummary()
    {
        $instituteId = tenant_id();
        $country = tenant_country($instituteId);
        $currency = tenant_currency($instituteId);
        $period = request()->query('period', (string) date('Y'));

        $data = $this->taxReportService->tdsSummary($instituteId, $period, $country);

        return view('settings.tax.reports.tds-summary', array_merge($data, compact('country', 'currency')));
    }

    public function advanceTax()
    {
        $instituteId = tenant_id();
        $country = tenant_country($instituteId);
        $currency = tenant_currency($instituteId);
        $fy = request()->query('fy', (string) date('Y'));

        $data = $this->taxReportService->advanceTaxRegister($instituteId, $fy, $country);

        return view('settings.tax.reports.advance-tax', array_merge($data, compact('country', 'currency')));
    }

    public function corporateReturn()
    {
        $instituteId = tenant_id();
        $country = tenant_country($instituteId);
        $currency = tenant_currency($instituteId);
        $fy = request()->query('fy', (string) date('Y'));

        $data = $this->taxReportService->corporateTaxReturnData($instituteId, $fy, $country);

        return view('settings.tax.reports.corporate-return', array_merge($data, compact('country', 'currency')));
    }
}
