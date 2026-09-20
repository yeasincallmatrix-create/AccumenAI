<?php

namespace App\Http\Controllers\Settings;

use App\Enums\BusinessEntityType;
use App\Http\Controllers\Controller;
use App\Services\Accounting\BusinessEntityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessEntityController extends Controller
{
    public function __construct(
        protected BusinessEntityService $service
    ) {}

    /**
     * Show entity type selector.
     */
    public function index(): View
    {
        $type = $this->service->getType((int) tenant_id());

        return view('settings.business-entity.index', [
            'type' => $type,
            'options' => BusinessEntityType::options(),
        ]);
    }

    /**
     * Save entity type and redirect to the entity-specific page.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_entity_type' => 'required|in:'.implode(',', array_keys(BusinessEntityType::options())),
        ]);

        $type = BusinessEntityType::from($validated['business_entity_type']);
        $this->service->setType((int) tenant_id(), $type);

        return redirect()
            ->route($type->settingsRoute())
            ->with('status', 'Business entity type set to '.$type->label().'.');
    }

    public function soleProprietorship(): View
    {
        $type = $this->service->getType((int) tenant_id());
        abort_unless($type === BusinessEntityType::SOLE_PROPRIETORSHIP, 404);

        return view('settings.business-entity.sole-proprietorship', [
            'suggested' => $this->service->suggestedAccounts($type),
        ]);
    }

    public function partnership(): View
    {
        $type = $this->service->getType((int) tenant_id());
        abort_unless($type === BusinessEntityType::PARTNERSHIP, 404);

        return view('settings.business-entity.partnership', [
            'suggested' => $this->service->suggestedAccounts($type),
            'partners' => \App\Models\Partner::where('institute_id', tenant_id())->get(),
        ]);
    }

    public function privateLimited(): View
    {
        $type = $this->service->getType((int) tenant_id());
        abort_unless($type === BusinessEntityType::PRIVATE_LIMITED, 404);

        return view('settings.business-entity.private-limited', [
            'suggested' => $this->service->suggestedAccounts($type),
            'shareholders' => \App\Models\Shareholder::where('institute_id', tenant_id())->get(),
        ]);
    }
}
