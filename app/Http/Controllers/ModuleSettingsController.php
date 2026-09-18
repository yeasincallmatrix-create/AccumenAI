<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use App\Services\ModuleAccessService;
use Illuminate\Http\Request;

class ModuleSettingsController extends Controller
{
    public function index()
    {
        $institute = $this->resolveInstitute();
        $moduleService = app(ModuleAccessService::class);
        $subModules = $moduleService->getMedicalSubModules();
        $enabledKeys = $moduleService->getEnabledModules($institute);

        return view('settings.modules', compact('subModules', 'institute', 'enabledKeys'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'modules'   => 'array',
            'modules.*' => 'string',
        ]);

        $institute = $this->resolveInstitute();
        $moduleService = app(ModuleAccessService::class);
        $enabled = $request->input('modules', []);
        $actorId = $request->user()?->id;
        $reason = $request->input('reason');

        // Get all medical sub-modules
        $subModules = $moduleService->getMedicalSubModules();

        foreach ($subModules as $sub) {
            $shouldEnable = in_array($sub->key, $enabled, true);

            // SEC-04: route every toggle through the service layer (same
            // enableModule()/disableModule() methods as the admin path) so
            // industry validation, actor attribution, audit logging, and
            // cache invalidation apply. No raw override writes here.
            if ($shouldEnable) {
                $moduleService->enableModule($institute, $sub->key, $actorId, $reason);
            } else {
                $moduleService->disableModule($institute, $sub->key, $actorId, $reason);
            }
        }

        $moduleService->flushCache($institute->id);

        return back()->with('success', 'Medical sub-modules updated successfully.');
    }

    private function resolveInstitute(): Institute
    {
        $id = \App\Support\TenantContext::id();
        return Institute::withoutGlobalScopes()->findOrFail($id);
    }
}
