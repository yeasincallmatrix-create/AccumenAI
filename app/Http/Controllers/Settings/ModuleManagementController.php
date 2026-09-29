<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\ModuleRegistry;
use App\Services\IndustrySubcategoryService;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleManagementController extends Controller
{
    public function __construct(protected ModuleAccessService $moduleAccess) {}

    public function index(Request $request)
    {
        $institute = $this->resolveInstitute();
        $user = $request->user();
        $hiddenKeys = $this->hiddenModuleKeys($institute);

        $modules = ModuleRegistry::orderBy('parent_key')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($m) => $this->moduleAccess->isIndustryCompatible($institute, $m->key))
            ->filter(fn ($m) => ! in_array($m->key, $hiddenKeys, true))
            ->values();

        $enabledKeys = $this->moduleAccess->getEnabledModules($institute);

        $matrixOn = $institute->package_id && $institute->industry
            ? $this->moduleAccess->hasIndustryMatrix((int) $institute->package_id, (string) $institute->industry)
            : false;

        $enriched = $modules->map(function ($m) use ($user, $enabledKeys, $institute, $matrixOn) {
            $enabled = in_array($m->key, $enabledKeys, true);

            $canToggle = $this->userCanToggleModule($user, $m->key);
            $canAccess = $this->moduleAccess->isPackageAllowed($m->key, $institute->id);

            // Industry-matrix override: off in this package × industry
            // (optional/hidden) reads as upgrade-gated even when the base
            // package set would allow it. Unconfigured industries keep
            // legacy behavior.
            $matrixOff = false;
            if ($matrixOn && $institute->package_id && $institute->industry) {
                $category = $this->moduleAccess->industryMatrixCategory(
                    (int) $institute->package_id,
                    (string) $institute->industry,
                    $m->key
                );
                $matrixOff = $category !== null && ! in_array($category, ['mandatory', 'default'], true);
            }

            $parentOk = true;
            if (! empty($m->parent_key)) {
                $parentOk = in_array($m->parent_key, $enabledKeys, true);
            }

            $reason = null;
            if (! $canToggle) {
                $reason = 'permission_denied';
            } elseif (! $canAccess || $matrixOff) {
                $reason = 'upgrade_required';
            } elseif (! $parentOk) {
                $reason = 'parent_disabled';
            }

            return (object) [
                'key' => $m->key,
                'name' => $m->name,
                'icon' => $m->icon,
                'parent_key' => $m->parent_key,
                'coming_soon' => (bool) $m->coming_soon,
                'enabled' => $enabled,
                'can_toggle' => $canToggle && $canAccess && ! $matrixOff && $parentOk,
                'reason_disabled' => $reason,
            ];
        });

        $grouped = $enriched->groupBy(fn ($m) => $m->parent_key ?: '_root');

        return view('settings.modules', compact('grouped', 'enriched'));
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'module_key' => 'required|string|exists:module_registry,key',
            'enabled' => 'required|boolean',
        ]);

        $institute = $this->resolveInstitute();
        $user = $request->user();
        $key = $request->input('module_key');
        $enable = (bool) $request->input('enabled');

        // Hidden Rule — parked by the platform for this sub-category: the
        // tenant UI never lists it, so no legitimate toggle can arrive; block
        // it at the backend too rather than trusting the UI.
        if (in_array($key, $this->hiddenModuleKeys($institute), true)) {
            return response()->json(['error' => 'This module is not available for your organization.'], 403);
        }

        if (! $this->userCanToggleModule($user, $key)) {
            return response()->json(['error' => 'Permission denied.'], 403);
        }

        if ($enable && ! $this->moduleAccess->isPackageAllowed($key, $institute->id)) {
            return response()->json(['error' => 'Your package does not include this module.'], 403);
        }

        // Industry-matrix gate: optional/hidden in this package × industry
        // cannot be tenant-enabled (upgrade path instead).
        if ($enable && $institute->package_id && $institute->industry) {
            $category = $this->moduleAccess->industryMatrixCategory(
                (int) $institute->package_id,
                (string) $institute->industry,
                $key
            );
            if ($category !== null && ! in_array($category, ['mandatory', 'default'], true)) {
                return response()->json(['error' => 'Your package does not include this module for your industry.'], 403);
            }
        }

        if ($enable) {
            $module = ModuleRegistry::where('key', $key)->first();
            if ($module && ! empty($module->parent_key)) {
                $enabledKeys = $this->moduleAccess->getEnabledModules($institute);
                $parentEnabled = in_array($module->parent_key, $enabledKeys, true);
                if (! $parentEnabled) {
                    return response()->json([
                        'error' => "Cannot enable: parent module '{$module->parent_key}' is disabled.",
                    ], 422);
                }
            }
        }

        if ($enable) {
            $this->moduleAccess->enableModule($institute, $key, $user->id, 'Toggled via Module Management UI');
        } else {
            $this->moduleAccess->disableModule($institute, $key, $user->id, 'Toggled via Module Management UI');
        }

        if (! $enable) {
            $children = ModuleRegistry::where('parent_key', $key)->pluck('key');
            foreach ($children as $childKey) {
                $this->moduleAccess->disableModule($institute, $childKey, $user->id, "Parent '{$key}' disabled");
            }
        }

        return response()->json([
            'status' => 'ok',
            'module_key' => $key,
            'enabled' => $enable,
        ]);
    }

    /**
     * Hidden Rule — module keys the platform parked in the 'hidden' bucket for
     * this institute's sub-category. Empty when the institute has no
     * sub-category, so nothing changes for unmatched tenants.
     *
     * @return array<int, string>
     */
    protected function hiddenModuleKeys(Institute $institute): array
    {
        if (! $institute->subcategory_key) {
            return [];
        }

        return app(IndustrySubcategoryService::class)
            ->getModules($institute->industry ?? '', $institute->subcategory_key)['hidden'] ?? [];
    }

    protected function userCanToggleModule($user, string $moduleKey): bool
    {
        if ($user->hasRole('institute-owner')) {
            return true;
        }

        if ($user->hasPermission('institute.settings.module.toggle')) {
            return true;
        }

        return false;
    }

    protected function resolveInstitute(): Institute
    {
        $id = TenantContext::id();

        return Institute::withoutGlobalScopes()->findOrFail($id);
    }
}
