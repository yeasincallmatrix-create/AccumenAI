<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Admission;
use App\Models\Medical\Appointment;
use App\Models\Medical\Invoice;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabResult;
use App\Models\Medical\Patient;
use App\Models\Medical\PharmacyDispense;
use App\Models\Medical\TpaClaim;
use App\Services\Medical\BillingService;
use App\Services\Medical\ExpiryAlertService;
use App\Services\Medical\PharmacyStockService;
use App\Services\Medical\TpaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ReportController extends MedicalController implements HasMiddleware
{
    /**
     * Methods mirror the Phase 0 reports/* routes exactly:
     * daily, monthly, revenue, clinical, pharmacy, lab, tpa, regulatory.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_reports.view', only: [
                'daily', 'monthly', 'revenue', 'clinical', 'pharmacy', 'lab', 'tpa', 'regulatory',
            ]),
        ];
    }

    protected BillingService $billingService;

    protected TpaService $tpaService;

    protected PharmacyStockService $stockService;

    protected ExpiryAlertService $expiryService;

    public function __construct(
        BillingService $billingService,
        TpaService $tpaService,
        PharmacyStockService $stockService,
        ExpiryAlertService $expiryService
    ) {
        $this->billingService = $billingService;
        $this->tpaService = $tpaService;
        $this->stockService = $stockService;
        $this->expiryService = $expiryService;
    }

    /**
     * Today's pulse: revenue + invoice breakdown.
     */
    public function daily()
    {
        $instituteId = $this->instituteId();
        $revenue = $this->billingService->getRevenueSummary($instituteId, 'today');

        $invoices = Invoice::where('institute_id', $instituteId)
            ->whereDate('invoice_date', today())
            ->with(['patient'])
            ->orderBy('invoice_date', 'desc')
            ->get();

        return view('medical.reports.daily', compact('revenue', 'invoices'));
    }

    /**
     * Last-30-days overview.
     */
    public function monthly()
    {
        $instituteId = $this->instituteId();
        $revenue = $this->billingService->getRevenueSummary($instituteId, 'month');

        $byDay = Invoice::where('institute_id', $instituteId)
            ->whereDate('invoice_date', '>=', now()->subDays(30))
            ->selectRaw('DATE(invoice_date) as day, COUNT(*) as count, SUM(total) as revenue, SUM(paid_amount) as collected')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return view('medical.reports.monthly', compact('revenue', 'byDay'));
    }

    /**
     * Revenue by invoice type + status breakdown.
     */
    public function revenue(Request $request)
    {
        $instituteId = $this->instituteId();
        $period = in_array($request->get('period'), ['today', 'week', 'month'], true)
            ? $request->get('period')
            : 'month';

        $revenue = $this->billingService->getRevenueSummary($instituteId, $period);

        $invoices = Invoice::where('institute_id', $instituteId)
            ->whereDate('invoice_date', '>=', $this->periodStart($period))
            ->get();

        $breakdown = [
            'total' => $invoices->count(),
            'paid' => $invoices->where('status', 'paid')->count(),
            'pending' => $invoices->where('status', 'pending')->count(),
            'partial' => $invoices->where('status', 'partial')->count(),
            'cancelled' => $invoices->where('status', 'cancelled')->count(),
            'collected' => $invoices->sum('paid_amount'),
            'outstanding' => $invoices->sum('due_amount'),
        ];

        return view('medical.reports.revenue', compact('revenue', 'breakdown', 'period'));
    }

    public function financial(Request $request)
    {
        return $this->revenue($request);
    }

    /**
     * Clinical activity for a date range.
     */
    public function clinical(Request $request)
    {
        $instituteId = $this->instituteId();
        $fromDate = $request->get('from_date', now()->subDays(30)->format('Y-m-d'));
        $toDate = $request->get('to_date', now()->format('Y-m-d'));

        // Patient statistics.
        $newPatients = Patient::where('institute_id', $instituteId)
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->count();

        $appointments = Appointment::where('institute_id', $instituteId)
            ->whereDate('appointment_date', '>=', $fromDate)
            ->whereDate('appointment_date', '<=', $toDate)
            ->get();

        $admissions = Admission::where('institute_id', $instituteId)
            ->whereDate('admission_date', '>=', $fromDate)
            ->whereDate('admission_date', '<=', $toDate)
            ->get();

        $appointmentStats = [
            'total' => $appointments->count(),
            'completed' => $appointments->where('status', 'completed')->count(),
            'cancelled' => $appointments->where('status', 'cancelled')->count(),
            'no_show' => $appointments->where('status', 'no_show')->count(),
        ];

        $admissionStats = [
            'total' => $admissions->count(),
            'active' => $admissions->where('status', 'active')->count(),
            'discharged' => $admissions->where('status', 'discharged')->count(),
            'expired' => $admissions->where('status', 'expired')->count(),
        ];

        return view('medical.reports.clinical', compact(
            'newPatients', 'appointmentStats', 'admissionStats', 'fromDate', 'toDate'
        ));
    }

    /**
     * Pharmacy: low stock, expiry and top-dispensed medicines.
     */
    public function pharmacy()
    {
        $instituteId = $this->instituteId();

        $lowStock = $this->stockService->getLowStockItems($instituteId);
        $expiryAlerts = $this->expiryService->checkAndAlert($instituteId);

        // Top dispensed medicines via the stock batches actually deducted.
        $topMedicines = PharmacyDispense::where('pharmacy_dispenses.institute_id', $instituteId)
            ->join('pharmacy_stock', 'pharmacy_stock.id', '=', 'pharmacy_dispenses.stock_id')
            ->join('medicines', 'medicines.id', '=', 'pharmacy_stock.medicine_id')
            ->selectRaw('medicines.id, medicines.generic_name, medicines.brand_name, SUM(pharmacy_dispenses.quantity_dispensed) as total_dispensed')
            ->groupBy('medicines.id', 'medicines.generic_name', 'medicines.brand_name')
            ->orderByDesc('total_dispensed')
            ->limit(10)
            ->get();

        return view('medical.reports.pharmacy', compact('lowStock', 'expiryAlerts', 'topMedicines'));
    }

    /**
     * Lab volumes + most-ordered tests.
     */
    public function lab(Request $request)
    {
        $instituteId = $this->instituteId();
        $fromDate = $request->get('from_date', now()->subDays(30)->format('Y-m-d'));
        $toDate = $request->get('to_date', now()->format('Y-m-d'));

        $orders = LabOrder::where('institute_id', $instituteId)
            ->whereDate('order_date', '>=', $fromDate)
            ->whereDate('order_date', '<=', $toDate)
            ->get();

        $stats = [
            'total_orders' => $orders->count(),
            'completed' => $orders->where('status', 'completed')->count(),
            'pending' => $orders->whereIn('status', ['ordered', 'collected', 'processing'])->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
        ];

        // Most-ordered tests (scoped to this institute's orders).
        $popularTests = LabResult::selectRaw('lab_test_id, count(*) as total')
            ->whereHas('labOrder', function ($q) use ($instituteId) {
                $q->where('institute_id', $instituteId);
            })
            ->groupBy('lab_test_id')
            ->orderByDesc('total')
            ->limit(10)
            ->with('labTest')
            ->get();

        return view('medical.reports.lab', compact('stats', 'popularTests', 'fromDate', 'toDate'));
    }

    /**
     * TPA claim book.
     */
    public function tpa()
    {
        $instituteId = $this->instituteId();
        $stats = $this->tpaService->getClaimStats($instituteId);

        $claims = TpaClaim::where('institute_id', $instituteId)
            ->with(['patient', 'invoice'])
            ->orderBy('claim_date', 'desc')
            ->limit(100)
            ->get();

        return view('medical.reports.tpa', compact('stats', 'claims'));
    }

    /**
     * Regulatory snapshot: census, outcomes and service volumes.
     */
    public function regulatory(Request $request)
    {
        $instituteId = $this->instituteId();
        $fromDate = $request->get('from_date', now()->subDays(30)->format('Y-m-d'));
        $toDate = $request->get('to_date', now()->format('Y-m-d'));

        $admissions = Admission::where('institute_id', $instituteId)
            ->whereDate('admission_date', '>=', $fromDate)
            ->whereDate('admission_date', '<=', $toDate)
            ->get();

        $labOrders = LabOrder::where('institute_id', $instituteId)
            ->whereDate('order_date', '>=', $fromDate)
            ->whereDate('order_date', '<=', $toDate)
            ->count();

        $stats = [
            'admissions' => $admissions->count(),
            'discharges' => $admissions->where('status', 'discharged')->count(),
            'deaths' => $admissions->where('status', 'expired')->count(),
            'current_ipd' => Admission::where('institute_id', $instituteId)
                ->where('status', 'active')->count(),
            'lab_orders' => $labOrders,
            'new_patients' => Patient::where('institute_id', $instituteId)
                ->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $toDate)
                ->count(),
        ];

        return view('medical.reports.regulatory', compact('stats', 'fromDate', 'toDate'));
    }

    private function periodStart(string $period): string
    {
        return match ($period) {
            'today' => today()->format('Y-m-d'),
            'week' => now()->subDays(7)->format('Y-m-d'),
            default => now()->subDays(30)->format('Y-m-d'),
        };
    }
}
