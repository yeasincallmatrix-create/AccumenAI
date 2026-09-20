<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\IssueSharesRequest;
use App\Http\Requests\Settings\TransferSharesRequest;
use App\Http\Requests\Settings\UpdateCapitalSettingsRequest;
use App\Models\Institute;
use App\Models\ShareCapitalTransaction;
use App\Models\ShareCertificate;
use App\Services\Accounting\ShareCapitalService;
use Illuminate\Support\Facades\Gate;

class ShareCapitalController extends Controller
{
    public function __construct(protected ShareCapitalService $service) {}

    public function index()
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        $type = app(\App\Services\Accounting\BusinessEntityService::class)->getType(tenant_id());
        abort_unless($type === \App\Enums\BusinessEntityType::PRIVATE_LIMITED, 404,
            'Private Limited module not enabled.');

        $summary = $this->service->getCapitalSummary(tenant_id());

        $transactions = ShareCapitalTransaction::where('institute_id', tenant_id())
            ->with(['shareholder', 'fromShareholder', 'toShareholder'])
            ->orderBy('transaction_date', 'desc')
            ->paginate(20);

        return view('settings.business-entity.share-capital.index',
            compact('summary', 'transactions'));
    }

    public function settings()
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);
        $institute = Institute::find(tenant_id());

        return view('settings.business-entity.share-capital.settings', compact('institute'));
    }

    public function updateSettings(UpdateCapitalSettingsRequest $request)
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);
        Institute::where('id', tenant_id())->update($request->validated());

        return redirect()->route('settings.share-capital.index')
            ->with('success', 'Capital settings updated.');
    }

    public function issue(IssueSharesRequest $request)
    {
        Gate::authorize('create', ShareCapitalTransaction::class);

        try {
            $this->service->issueShares(
                tenant_id(), $request->shareholder_id, $request->shares,
                $request->face_value, $request->premium_per_share ?? 0, $request->notes
            );

            return back()->with('success', 'Shares issued successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function transfer(TransferSharesRequest $request)
    {
        Gate::authorize('create', ShareCapitalTransaction::class);

        try {
            $this->service->transferShares(
                tenant_id(), $request->from_shareholder_id, $request->to_shareholder_id,
                $request->shares, $request->face_value, $request->notes
            );

            return back()->with('success', 'Shares transferred successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function certificates()
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        $certificates = ShareCertificate::where('institute_id', tenant_id())
            ->with('shareholder')
            ->orderBy('issue_date', 'desc')
            ->paginate(20);

        return view('settings.business-entity.share-capital.certificates',
            compact('certificates'));
    }
}
