<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\FinalizeReconciliationRequest;
use App\Models\ChartOfAccount;
use App\Models\TaxReturnReconciliation;
use App\Services\Accounting\TaxReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaxReconciliationController extends Controller
{
    public function __construct(protected TaxReconciliationService $service) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', ChartOfAccount::class);
        $fy = $request->input('fy', $this->currentFY());
        $computed = $this->service->compute(tenant_id(), $fy);
        $saved = TaxReturnReconciliation::where('institute_id', tenant_id())
            ->where('financial_year', $fy)
            ->first();

        return view('settings.tax.reconciliation.index', compact('computed', 'saved', 'fy'));
    }

    public function finalize(FinalizeReconciliationRequest $request)
    {
        Gate::authorize('viewAny', ChartOfAccount::class);
        $this->service->finalize(tenant_id(), $request->financial_year, $request->validated());

        return back()->with('success', 'Reconciliation finalized.');
    }

    public function markFiled(Request $request, TaxReturnReconciliation $reconciliation)
    {
        abort_unless((int) $reconciliation->institute_id === tenant_id(), 404);
        $validated = $request->validate([
            'filing_date' => 'nullable|date',
            'acknowledgment_no' => 'nullable|string|max:50',
        ]);
        $this->service->markFiled($reconciliation, $validated);

        return back()->with('success', 'Marked as filed.');
    }

    protected function currentFY(): string
    {
        $m = (int) now()->format('m');
        $y = (int) now()->format('Y');
        $s = $m >= 7 ? $y : $y - 1;

        return $s . '-' . ($s + 1);
    }
}
