<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use App\Services\ModuleAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Get all medical sub-modules
        $subModules = $moduleService->getMedicalSubModules();

        foreach ($subModules as $sub) {
            $shouldEnable = in_array($sub->key, $enabled, true);

            if ($shouldEnable) {
                DB::table('institute_module_overrides')->updateOrInsert(
                    ['institute_id' => $institute->id, 'module_key' => $sub->key],
                    ['enabled' => true, 'updated_at' => now()]
                );
            } else {
                DB::table('institute_module_overrides')
                    ->where('institute_id', $institute->id)
                    ->where('module_key', $sub->key)
                    ->update(['enabled' => false, 'updated_at' => now()]);
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
