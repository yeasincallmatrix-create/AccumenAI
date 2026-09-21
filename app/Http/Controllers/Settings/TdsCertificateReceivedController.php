<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RecordCertificateReceivedRequest;
use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\TdsCertificateReceived;
use App\Services\Accounting\TdsCertificateReceivedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TdsCertificateReceivedController extends Controller
{
    public function __construct(protected TdsCertificateReceivedService $service) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', ChartOfAccount::class);
        $fy = $request->input('fy', $this->currentFY());
        $certs = TdsCertificateReceived::where('institute_id', tenant_id())
            ->where('financial_year', $fy)
            ->with('party')
            ->orderBy('certificate_date', 'desc')
            ->paginate(20);

        return view('settings.tax.tds-certificates-received.index', compact('certs', 'fy'));
    }

    public function create()
    {
        Gate::authorize('viewAny', ChartOfAccount::class);
        $parties = Party::where('institute_id', tenant_id())->orderBy('name')->get(['id', 'name']);

        return view('settings.tax.tds-certificates-received.create', compact('parties'));
    }

    public function store(RecordCertificateReceivedRequest $request)
    {
        Gate::authorize('viewAny', ChartOfAccount::class);
        $party = Party::where('institute_id', tenant_id())->where('id', $request->party_id)->first();
        abort_unless($party, 403, 'Party does not belong to this institute.');
        $this->service->record(tenant_id(), $request->validated());

        return redirect()->route('settings.tds-certificates-received.index')
            ->with('success', 'Certificate recorded and matched.');
    }

    public function verify(TdsCertificateReceived $certificate)
    {
        abort_unless((int) $certificate->institute_id === tenant_id(), 404);
        $this->service->verify($certificate);

        return back()->with('success', 'Certificate verified.');
    }

    protected function currentFY(): string
    {
        $m = (int) now()->format('m');
        $y = (int) now()->format('Y');
        $s = $m >= 7 ? $y : $y - 1;

        return $s . '-' . ($s + 1);
    }
}
