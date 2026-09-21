<?php

namespace App\Http\Controllers\Settings;

use App\Enums\BusinessEntityType;
use App\Http\Controllers\Controller;
use App\Services\Accounting\BusinessEntityService;
use App\Services\Accounting\TenantAccountingModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdvancedAccountingSettingController extends Controller
{
    public function __construct(
        protected TenantAccountingModeService $mode,
        protected BusinessEntityService $entityService
    ) {}

    public function index(): View
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        $enabled = $this->mode->isAdvancedEnabled();
        $entityType = $this->entityService->getType((int) tenant_id());

        return view('settings.advanced-accounting', [
            'enabled' => $enabled,
            'entityType' => $entityType,
            'entityOptions' => BusinessEntityType::advancedOptions(),
            'suggestedAccounts' => $enabled
                ? $this->entityService->suggestedAccounts($entityType)
                : [],
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        $newState = $this->mode->toggle((int) tenant_id());

        // Turning OFF resets entity type (data safety).
        if (! $newState) {
            $this->entityService->resetToSingleEntity((int) tenant_id());
        }

        $message = $newState
            ? 'Advanced Accounting enabled. Entity type options unlocked.'
            : 'Advanced Accounting disabled. Reset to Single Entity mode.';

        return back()->with('status', $message);
    }

    public function updateEntityType(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', \App\Models\ChartOfAccount::class);

        abort_unless($this->mode->isAdvancedEnabled(), 403,
            'Advanced Accounting must be enabled first.');

        $validated = $request->validate([
            'business_entity_type' => 'required|in:'.implode(',', array_keys(BusinessEntityType::advancedOptions())),
        ]);

        $type = BusinessEntityType::from($validated['business_entity_type']);
        $this->entityService->setType((int) tenant_id(), $type);

        return redirect()
            ->route($type->settingsRoute())
            ->with('status', 'Entity type set to '.$type->label().'.');
    }
}
