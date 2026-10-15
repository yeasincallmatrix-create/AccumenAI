<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Unified invoice read endpoints (Finance + Sales soft merge).
 *
 * List is rendered by the UnifiedInvoiceList Livewire component; this
 * controller only gates access and hydrates the show view. Authorization
 * mirrors Finance: route middleware permission:finance.view +
 * module_access:finance (registered in institute_modules.php inside the
 * tenant stack), plus InvoicePolicy::view for per-model tenant checks.
 * No constructor middleware — the route group carries the stack.
 */
class UnifiedInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        return view('institute.invoices.index');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load([
            'items.coa', 'payments', 'journal', 'installments',
            'salesOrder', 'salesDelivery', 'student', 'party',
            'currency',
        ]);

        return view('institute.invoices.show', compact('invoice'));
    }
}
