<?php

namespace App\Http\Controllers;

use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff invitation flow.
 *
 * An authorized member (owner/admin with staff.manage) creates a global Staff
 * Account inside the active organization. The account is created via
 * UserAccountService::createStaffFromInvitation() and joined to the active
 * organization with the chosen (non-owner) role through MembershipService.
 */
class StaffInvitationController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();

        $institutionId = $this->resolveInstitutionId($user);

        $roles = Role::query()
            ->where('slug', '!=', 'institute-owner')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('staff.invite', [
            'roles' => $roles,
            'members' => $this->membersFor($institutionId),
            'institutionId' => $institutionId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+?\d{4,20}$/', 'unique:users,phone'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        $role = Role::query()->findOrFail($data['role_id']);

        abort_if($role->slug === 'institute-owner', 422, 'Owners cannot be invited as staff.');

        $institutionId = $this->resolveInstitutionId($request->user());

        abort_if($institutionId === null, 422, 'No active organization selected.');

        $user = app(UserAccountService::class)->createStaffFromInvitation([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'preferred_language' => mawa_current_lang(),
            'password_hash' => app(\App\Services\Auth\PasswordService::class)->hash($data['password']),
            'status' => 'active',
        ]);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);
        }

        app(MembershipService::class)->assign($user, $institutionId, $role->id);

        return redirect()
            ->route('staff.invite')
            ->with('status', mawa_lang('staff.invited_ok', ['name' => $user->name]));
    }

    protected function resolveInstitutionId($user): ?int
    {
        if ($user instanceof InstituteUser) {
            return $user->institute_id;
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();

            return $membership?->institution_id;
        }

        return null;
    }

    protected function membersFor(?int $institutionId)
    {
        if ($institutionId === null) {
            return collect();
        }

        return Membership::query()
            ->where('institution_id', $institutionId)
            ->where('status', 'active')
            ->with(['user', 'role'])
            ->orderBy('id')
            ->get();
    }
}
