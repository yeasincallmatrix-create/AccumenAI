<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Invoice;
use App\Services\Medical\BillingService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Billing landing page (route: GET billing → medical.billing.index).
 *
 * Invoice CRUD + payments live on InvoiceController, which is what the
 * billing/* routes point at; this controller is the dashboard shell.
 */
class BillingController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_billing.view', only: ['index']),
        ];
    }

    protected BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    public function index()
    {
        $instituteId = $this->instituteId();
        $fence = $this->doctorFenceId();

        $revenue = $this->billingService->getRevenueSummary($instituteId, 'month', $fence);

        $recentInvoices = Invoice::where('institute_id', $instituteId)
            ->visibleToDoctor($instituteId, $fence)
            ->with(['patient'])
            ->orderBy('invoice_date', 'desc')
            ->limit(10)
            ->get();

        $dueInvoices = Invoice::where('institute_id', $instituteId)
            ->visibleToDoctor($instituteId, $fence)
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $outstanding = Invoice::where('institute_id', $instituteId)
            ->visibleToDoctor($instituteId, $fence)
            ->whereIn('status', ['pending', 'partial'])
            ->sum('due_amount');

        return view('medical.billing.dashboard', compact(
            'revenue',
            'recentInvoices',
            'dueInvoices',
            'outstanding'
        ));
    }
}
