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

        // Only global roles plus roles belonging to the current institute —
        // never another institute's roles (e.g. diagnostic-staff of 191 must
        // not be offered at Central Hospital 189).
        $roles = Role::query()
            ->where(function ($query) use ($institutionId) {
                $query->whereNull('institute_id');
                if ($institutionId !== null) {
                    $query->orWhere('institute_id', $institutionId);
                }
            })
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

    /**
     * Change a member's role within the current institute.
     */
    public function updateRole(Request $request, Membership $member): RedirectResponse
    {
        $institutionId = $this->resolveInstitutionId($request->user());
        abort_if($member->institution_id !== $institutionId, 403);

        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $role = Role::query()->findOrFail($data['role_id']);
        abort_if($role->slug === 'institute-owner', 422, 'Owners cannot be assigned via staff management.');

        app(MembershipService::class)->changeRole($member, $role->id);

        return redirect()
            ->route('staff.invite')
            ->with('status', mawa_lang('staff.role_updated_ok', ['name' => $member->user?->name ?? '']));
    }

    /**
     * Remove a staff member from the current institute.
     */
    public function destroy(Request $request, Membership $member): RedirectResponse
    {
        $institutionId = $this->resolveInstitutionId($request->user());
        abort_if($member->institution_id !== $institutionId, 403);
        abort_if($member->hasRole('institute-owner'), 422, 'The institute owner cannot be removed.');
        abort_if((int) $member->user_id === (int) $request->user()?->getKey(), 422, 'You cannot remove yourself.');

        $name = $member->user?->name ?? '';
        $member->delete();

        return redirect()
            ->route('staff.invite')
            ->with('status', mawa_lang('staff.removed_ok', ['name' => $name]));
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
