<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\InstituteModuleEntitlement;
use App\Models\ModuleRegistry;
use App\Services\ModuleAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstituteModuleEntitlementController extends Controller
{
    public function index(Institute $institute): View
    {
        $institute->load(['package']);

        $service = app(ModuleAccessService::class);
        $packageModules = [];
        if ($institute->package) {
            $packageModules = $service->getPackageModules($institute->package);
        } else {
            $free = \App\Models\SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->first();
            if ($free) {
                $packageModules = $service->getPackageModules($free);
            }
        }

        $resolved = $service->resolveEnabled($institute);

        // Individual entitlements (all non-deleted) with history context
        $entitlements = InstituteModuleEntitlement::where('institute_id', $institute->id)
            ->with(['grantedBy'])
            ->orderByDesc('created_at')
            ->get();

        $allModules = ModuleRegistry::where('status', 'active')->orderBy('sort_order')->get()->keyBy('key');

        // Effective access map for status display
        $effectiveMap = $resolved;
        $enabledModules = array_keys(array_filter($resolved));

        // Module access history (reuse module_access_logs, tenant-associated)
        $history = \App\Models\ModuleAccessLog::where('institute_id', $institute->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // For dependency messaging
        $packageModuleKeys = array_keys(array_filter($packageModules));

        return view('admin.institutes.entitlements.index', compact('institute', 'packageModules', 'resolved', 'entitlements', 'allModules', 'effectiveMap', 'enabledModules', 'history', 'packageModuleKeys'));
    }

    public function create(Institute $institute): View
    {
        $institute->load(['package']);

        $modules = ModuleRegistry::where('status', 'active')->orderBy('sort_order')->get();

        // Filter for UI hint: mark industry-incompatible modules disabled
        $compatible = [];
        $service = app(ModuleAccessService::class);
        // Use reflection to access isIndustryCompatible (protected) via public wrapper through resolve? Simpler: duplicate logic
        foreach ($modules as $m) {
            $compatible[$m->key] = $this->isIndustryCompatible($institute, $m->key);
        }

        return view('admin.institutes.entitlements.create', compact('institute', 'modules', 'compatible'));
    }

    public function store(Request $request, Institute $institute): RedirectResponse
    {
        $validated = $request->validate([
            'module_key' => ['required', 'string', 'max:60', Rule::exists('module_registry', 'key')],
            'is_grant' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['active', 'trialing', 'pending'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'trial_starts_at' => ['nullable', 'date'],
            'trial_ends_at' => ['nullable', 'date', 'after_or_equal:trial_starts_at'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'yearly_price' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'yearly', 'one_time'])],
            'auto_renew' => ['nullable', 'boolean'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Server-side industry compatibility — never trust UI
        if (! $this->isIndustryCompatible($institute, $validated['module_key'])) {
            return back()->withErrors(['module_key' => 'Module is incompatible with institute industry.'])->withInput();
        }

        $service = app(ModuleAccessService::class);

        // Module exists already validated; service will throw ValidationException if not
        try {
            $service->grantModule($institute, $validated['module_key'], [
                'status' => $validated['status'],
                'is_grant' => (bool) $validated['is_grant'],
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'trial_starts_at' => $validated['trial_starts_at'] ?? null,
                'trial_ends_at' => $validated['trial_ends_at'] ?? null,
                'monthly_price' => $validated['monthly_price'] ?? null,
                'yearly_price' => $validated['yearly_price'] ?? null,
                'billing_cycle' => $validated['billing_cycle'] ?? null,
                'auto_renew' => (bool) ($validated['auto_renew'] ?? false),
                'discount_percent' => $validated['discount_percent'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], $request->user()?->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }

        return redirect()->route('admin.institutes.entitlements.index', $institute)->with('status', 'Entitlement granted.');
    }

    public function destroy(Request $request, Institute $institute, InstituteModuleEntitlement $entitlement): RedirectResponse
    {
        if ((int) $entitlement->institute_id !== (int) $institute->id) {
            abort(404);
        }

        app(ModuleAccessService::class)->revokeModule($institute, $entitlement->module_key, $request->user()?->id);

        return redirect()->route('admin.institutes.entitlements.index', $institute)->with('status', 'Entitlement revoked.');
    }

    public function extend(Request $request, Institute $institute, InstituteModuleEntitlement $entitlement): RedirectResponse
    {
        if ((int) $entitlement->institute_id !== (int) $institute->id) {
            abort(404);
        }

        $validated = $request->validate([
            'extend_option' => ['nullable', 'string', Rule::in(['1m','3m','6m','1y','custom'])],
            'ends_at' => ['nullable', 'date', 'after:today'],
            'trial_ends_at' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Resolve new expiry from option or custom date
        $newEndsAt = null;
        $newTrialEndsAt = null;
        $base = $entitlement->ends_at ? $entitlement->ends_at->copy() : now();
        if ($base->isPast()) {
            $base = now();
        }

        if ($request->filled('ends_at')) {
            $newEndsAt = \Illuminate\Support\Carbon::parse($validated['ends_at']);
        } elseif (!empty($validated['extend_option']) && $validated['extend_option'] !== 'custom') {
            $map = ['1m'=>1,'3m'=>3,'6m'=>6,'1y'=>12];
            $months = $map[$validated['extend_option']] ?? null;
            if ($months) {
                $newEndsAt = $base->copy()->addMonths($months);
            }
        }

        if ($request->filled('trial_ends_at')) {
            $newTrialEndsAt = \Illuminate\Support\Carbon::parse($validated['trial_ends_at']);
        }

        if (!$newEndsAt && !$newTrialEndsAt) {
            return back()->withErrors(['ends_at' => 'Select an extend option or provide a custom date.'])->withInput();
        }

        // Validate not bypassing industry (already checked via service)
        $attrs = [];
        if ($newEndsAt) {
            $attrs['ends_at'] = $newEndsAt;
        }
        if ($newTrialEndsAt) {
            $attrs['trial_ends_at'] = $newTrialEndsAt;
        }
        if (isset($validated['notes'])) {
            $attrs['notes'] = $validated['notes'];
        }

        // Prevent branch tampering etc — institute already from route, no branch involved
        app(ModuleAccessService::class)->extendEntitlement($entitlement, $attrs, $request->user()?->id);

        return redirect()->route('admin.institutes.entitlements.index', $institute)->with('status', 'Entitlement extended.');
    }

    private function isIndustryCompatible(Institute $institute, string $moduleKey): bool
    {
        if ($moduleKey === 'education' && $institute->industry !== null && $institute->industry !== 'education') {
            return false;
        }
        return true;
    }
}
