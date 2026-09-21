<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RecordTdsReceivableRequest;
use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\TdsReceivable;
use App\Services\Accounting\TdsReceivableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TdsReceivableController extends Controller
{
    public function __construct(protected TdsReceivableService $service) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', ChartOfAccount::class);
        $fy = $request->input('fy', $this->currentFY());
        $rows = TdsReceivable::where('institute_id', tenant_id())
            ->where('financial_year', $fy)
            ->with('party', 'certificate')
            ->orderBy('deduction_date', 'desc')
            ->paginate(20);
        $totals = [
            'gross' => (float) TdsReceivable::where('institute_id', tenant_id())->where('financial_year', $fy)->sum('gross_amount'),
            'tds' => (float) TdsReceivable::where('institute_id', tenant_id())->where('financial_year', $fy)->sum('tds_amount'),
            'pending' => TdsReceivable::where('institute_id', tenant_id())->where('financial_year', $fy)->where('status', 'pending_certificate')->count(),
        ];

        return view('settings.tax.tds-receivable.index', compact('rows', 'totals', 'fy'));
    }

    public function create()
    {
        Gate::authorize('viewAny', ChartOfAccount::class);
        $parties = Party::where('institute_id', tenant_id())->orderBy('name')->get(['id', 'name']);

        return view('settings.tax.tds-receivable.create', compact('parties'));
    }

    public function store(RecordTdsReceivableRequest $request)
    {
        Gate::authorize('viewAny', ChartOfAccount::class);
        $party = Party::where('institute_id', tenant_id())->where('id', $request->party_id)->first();
        abort_unless($party, 403, 'Party does not belong to this institute.');
        $this->service->record(tenant_id(), $request->validated());

        return redirect()->route('settings.tds-receivable.index')
            ->with('success', 'TDS receivable recorded.');
    }

    protected function currentFY(): string
    {
        $m = (int) now()->format('m');
        $y = (int) now()->format('Y');
        $s = $m >= 7 ? $y : $y - 1;

        return $s . '-' . ($s + 1);
    }
}
