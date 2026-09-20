<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreShareholderRequest;
use App\Models\Shareholder;
use App\Services\Accounting\ShareholderService;
use Illuminate\Support\Facades\Gate;

class ShareholderController extends Controller
{
    public function __construct(
        protected ShareholderService $service
    ) {}

    public function index()
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        $shareholders = Shareholder::where('institute_id', tenant_id())
            ->orderBy('name')->paginate(20);

        $totalShares = $this->service->totalShares(tenant_id());
        $totalPaidUp = $this->service->totalPaidUp(tenant_id());
        $directors = Shareholder::where('institute_id', tenant_id())
            ->directors()->count();

        return view('settings.business-entity.private-limited.index',
            compact('shareholders', 'totalShares', 'totalPaidUp', 'directors'));
    }

    public function create()
    {
        Gate::authorize('create', Shareholder::class);

        return view('settings.business-entity.private-limited.create');
    }

    public function store(StoreShareholderRequest $request)
    {
        Gate::authorize('create', Shareholder::class);

        $existing = (float) Shareholder::where('institute_id', tenant_id())->sum('share_percent');
        if ($existing + $request->share_percent > 100) {
            return back()->withErrors([
                'share_percent' => 'Total share cannot exceed 100%. Current: '.$existing.'%',
            ])->withInput();
        }

        $sh = $this->service->create(tenant_id(), $request->validated());

        return redirect()
            ->route('settings.business-entity.private-limited.index')
            ->with('success', "Shareholder {$sh->name} added.");
    }

    public function edit(Shareholder $shareholder)
    {
        Gate::authorize('update', $shareholder);
        abort_unless((int) $shareholder->institute_id === tenant_id(), 404);

        return view('settings.business-entity.private-limited.edit', compact('shareholder'));
    }

    public function update(StoreShareholderRequest $request, Shareholder $shareholder)
    {
        Gate::authorize('update', $shareholder);
        abort_unless((int) $shareholder->institute_id === tenant_id(), 404);

        $otherTotal = (float) Shareholder::where('institute_id', tenant_id())
            ->where('id', '!=', $shareholder->id)->sum('share_percent');
        if ($otherTotal + $request->share_percent > 100) {
            return back()->withErrors([
                'share_percent' => 'Total share cannot exceed 100%.',
            ])->withInput();
        }

        $this->service->update($shareholder, $request->validated());

        return redirect()
            ->route('settings.business-entity.private-limited.index')
            ->with('success', 'Shareholder updated.');
    }

    public function destroy(Shareholder $shareholder)
    {
        Gate::authorize('delete', $shareholder);
        abort_unless((int) $shareholder->institute_id === tenant_id(), 404);

        $this->service->delete($shareholder);

        return redirect()
            ->route('settings.business-entity.private-limited.index')
            ->with('success', 'Shareholder removed.');
    }
}
