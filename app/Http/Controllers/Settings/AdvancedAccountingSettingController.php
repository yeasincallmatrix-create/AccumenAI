<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Accounting\TenantAccountingModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvancedAccountingSettingController extends Controller
{
    public function __construct(
        protected TenantAccountingModeService $mode
    ) {}

    public function index(): View
    {
        return view('settings.advanced-accounting', [
            'enabled' => $this->mode->isAdvancedEnabled(),
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $newState = $this->mode->toggle((int) tenant_id());

        $message = $newState
            ? 'Advanced Accounting enabled. All features unlocked.'
            : 'Advanced Accounting disabled. Simple mode active.';

        return back()->with('status', $message);
    }
}
