<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\ModuleRegistry;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Services\UserModuleAccessService;
use App\Support\Workspace;
use Illuminate\Http\Request;

class InstituteUserModuleAccessController extends Controller
{
    public function index(Request $request, int $userId)
    {
        $user = $request->user();
        $institute = $this->resolveInstitute($user);
        abort_unless($institute, 404, 'Institute not found.');

        $targetUser = InstituteUser::where('institute_id', $institute->id)->find($userId);
        $userType = 'institute_user';
        if (! $targetUser) {
            $targetUser = User::findOrFail($userId);
            $userType = 'user';
        }

        $moduleService = app(ModuleAccessService::class);
        $userModuleService = app(UserModuleAccessService::class);

        return view('institute.users.module-access', [
            'institute' => $institute,
            'user' => $targetUser,
            'userType' => $userType,
            'allModules' => ModuleRegistry::orderBy('key')->get(),
            'instituteEnabled' => $moduleService->getEnabledModules($institute),
            'userOverrides' => $userModuleService->getForUser($institute, $targetUser),
        ]);
    }

    public function update(Request $request, int $userId)
    {
        $user = $request->user();
        $institute = $this->resolveInstitute($user);
        abort_unless($institute, 404, 'Institute not found.');

        $targetUser = InstituteUser::where('institute_id', $institute->id)->find($userId);
        $userType = 'institute_user';
        if (! $targetUser) {
            $targetUser = User::findOrFail($userId);
            $userType = 'user';
        }

        $data = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', 'exists:module_registry,key'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $selected = $data['modules'] ?? [];
        $allKeys = ModuleRegistry::pluck('key')->toArray();
        $userModuleService = app(UserModuleAccessService::class);
        $actorId = $user->getKey();

        foreach ($allKeys as $key) {
            $enabled = in_array($key, $selected, true);
            $userModuleService->setAccess($institute, $targetUser, $key, $enabled, $actorId, $data['reason'] ?? null);
        }

        return redirect()->route('institute.users.modules.index', $targetUser->getKey())
            ->with('status', 'Module access updated for ' . ($targetUser->name ?? $targetUser->email));
    }

    protected function resolveInstitute($user): ?Institute
    {
        if ($user instanceof InstituteUser) {
            return $user->institute;
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();
            if ($membership) {
                return $membership->institution ?? Institute::withoutGlobalScopes()->find($membership->institution_id);
            }
        }

        return null;
    }
}
