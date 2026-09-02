<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\ModuleRegistry;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Services\UserModuleAccessService;
use Illuminate\Http\Request;

class UserModuleAccessController extends Controller
{
    public function index(Institute $institute, $userId, $userType = null)
    {
        // Resolve user
        if ($userType === 'user') {
            $user = User::findOrFail($userId);
            $membership = \App\Models\Membership::where('user_id', $user->id)->where('institution_id', $institute->id)->first();
        } else {
            $user = InstituteUser::where('institute_id', $institute->id)->findOrFail($userId);
            $membership = null;
        }

        $moduleService = app(ModuleAccessService::class);
        $userModuleService = app(UserModuleAccessService::class);

        $allModules = ModuleRegistry::orderBy('key')->get();
        $instituteEnabled = $moduleService->getEnabledModules($institute);
        $userOverrides = $userModuleService->getForUser($institute, $user);

        return view('admin.users.module-access', compact('institute', 'user', 'userType', 'allModules', 'instituteEnabled', 'userOverrides'));
    }

    public function update(Request $request, Institute $institute, $userId, $userType = null)
    {
        if ($userType === 'user') {
            $user = User::findOrFail($userId);
        } else {
            $user = InstituteUser::where('institute_id', $institute->id)->findOrFail($userId);
        }

        $data = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'exists:module_registry,key'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $selected = $data['modules'] ?? [];
        $allKeys = ModuleRegistry::pluck('key')->toArray();
        $userModuleService = app(UserModuleAccessService::class);
        $actorId = $request->user()?->id;

        foreach ($allKeys as $key) {
            $enabled = in_array($key, $selected, true);
            $userModuleService->setAccess($institute, $user, $key, $enabled, $actorId, $data['reason'] ?? null);
        }

        return redirect()->route('admin.institutes.users.modules.index', [$institute, $user->getKey(), $user instanceof User ? 'user' : 'institute_user'])
            ->with('status', 'User module access updated.');
    }
}
