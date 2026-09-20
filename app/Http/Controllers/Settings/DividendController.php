<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DeclareDividendRequest;
use App\Http\Requests\Settings\RecordPayoutRequest;
use App\Models\Dividend;
use App\Models\DividendPayout;
use App\Services\Accounting\DividendService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DividendController extends Controller
{
    public function __construct(protected DividendService $service) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        $type = app(\App\Services\Accounting\BusinessEntityService::class)->getType(tenant_id());
        abort_unless($type === \App\Enums\BusinessEntityType::PRIVATE_LIMITED, 404,
            'Private Limited module not enabled.');

        $fy = $request->input('fy');

        $dividends = Dividend::where('institute_id', tenant_id())
            ->when($fy, fn ($q) => $q->where('financial_year', $fy))
            ->orderBy('declared_date', 'desc')
            ->paginate(20);

        $summary = $this->service->getSummary(tenant_id(), $fy);

        return view('settings.business-entity.dividend.index',
            compact('dividends', 'summary', 'fy'));
    }

    public function create()
    {
        Gate::authorize('create', Dividend::class);

        return view('settings.business-entity.dividend.create');
    }

    public function store(DeclareDividendRequest $request)
    {
        Gate::authorize('create', Dividend::class);

        try {
            $dividend = $this->service->declare(tenant_id(), $request->validated());

            return redirect()
                ->route('settings.dividend.show', $dividend)
                ->with('success', 'Dividend declared as draft.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show(Dividend $dividend)
    {
        Gate::authorize('view', $dividend);
        abort_unless((int) $dividend->institute_id === tenant_id(), 404);

        $dividend->load(['payouts.shareholder']);

        return view('settings.business-entity.dividend.show', compact('dividend'));
    }

    public function markDeclared(Dividend $dividend)
    {
        Gate::authorize('update', $dividend);
        abort_unless((int) $dividend->institute_id === tenant_id(), 404);

        try {
            $this->service->markDeclared($dividend);

            return back()->with('success', 'Dividend marked as declared.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function payPayout(RecordPayoutRequest $request, Dividend $dividend, DividendPayout $payout)
    {
        Gate::authorize('update', $dividend);
        abort_unless((int) $dividend->institute_id === tenant_id(), 404);
        abort_unless((int) $payout->dividend_id === $dividend->id, 404);

        try {
            $this->service->recordPayout($payout, $request->validated());

            return back()->with('success', 'Payout recorded.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function payAll(RecordPayoutRequest $request, Dividend $dividend)
    {
        Gate::authorize('update', $dividend);
        abort_unless((int) $dividend->institute_id === tenant_id(), 404);

        $count = $this->service->payAll($dividend, $request->validated());

        return back()->with('success', "{$count} payouts marked as paid.");
    }

    public function cancel(Dividend $dividend)
    {
        Gate::authorize('delete', $dividend);
        abort_unless((int) $dividend->institute_id === tenant_id(), 404);

        try {
            $this->service->cancel($dividend);

            return redirect()
                ->route('settings.dividend.index')
                ->with('success', 'Dividend cancelled.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function register(Request $request)
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        $fy = $request->input('fy');
        $register = $this->service->getRegister(tenant_id(), $fy);

        return view('settings.business-entity.dividend.register',
            compact('register', 'fy'));
    }
}
