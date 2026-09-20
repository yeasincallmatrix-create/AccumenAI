<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StorePartnerRequest;
use App\Models\Partner;
use App\Services\Accounting\PartnerService;
use Illuminate\Support\Facades\Gate;

class PartnerController extends Controller
{
    public function __construct(
        protected PartnerService $service
    ) {}

    public function index()
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        $partners = Partner::where('institute_id', tenant_id())
            ->orderBy('name')->paginate(20);

        $totalCapital = (float) Partner::where('institute_id', tenant_id())->sum('capital');
        $totalShare = (float) Partner::where('institute_id', tenant_id())->sum('share_percent');

        return view('settings.business-entity.partnership.index',
            compact('partners', 'totalCapital', 'totalShare'));
    }

    public function create()
    {
        Gate::authorize('create', Partner::class);

        return view('settings.business-entity.partnership.create');
    }

    public function store(StorePartnerRequest $request)
    {
        Gate::authorize('create', Partner::class);

        $existing = (float) Partner::where('institute_id', tenant_id())->sum('share_percent');
        if ($existing + $request->share_percent > 100) {
            return back()->withErrors([
                'share_percent' => 'Total share cannot exceed 100%. Current: '.$existing.'%',
            ])->withInput();
        }

        $partner = $this->service->create(tenant_id(), $request->validated());

        return redirect()
            ->route('settings.business-entity.partnership.index')
            ->with('success', "Partner {$partner->name} added.");
    }

    public function edit(Partner $partner)
    {
        Gate::authorize('update', $partner);
        abort_unless((int) $partner->institute_id === tenant_id(), 404);

        return view('settings.business-entity.partnership.edit', compact('partner'));
    }

    public function update(StorePartnerRequest $request, Partner $partner)
    {
        Gate::authorize('update', $partner);
        abort_unless((int) $partner->institute_id === tenant_id(), 404);

        $otherTotal = (float) Partner::where('institute_id', tenant_id())
            ->where('id', '!=', $partner->id)->sum('share_percent');
        if ($otherTotal + $request->share_percent > 100) {
            return back()->withErrors([
                'share_percent' => 'Total share cannot exceed 100%.',
            ])->withInput();
        }

        $this->service->update($partner, $request->validated());

        return redirect()
            ->route('settings.business-entity.partnership.index')
            ->with('success', 'Partner updated.');
    }

    public function destroy(Partner $partner)
    {
        Gate::authorize('delete', $partner);
        abort_unless((int) $partner->institute_id === tenant_id(), 404);

        $this->service->delete($partner);

        return redirect()
            ->route('settings.business-entity.partnership.index')
            ->with('success', 'Partner removed.');
    }
}
