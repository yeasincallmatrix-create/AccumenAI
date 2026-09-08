<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tenant role & permission management.
 *
 * Institute staff with `roles.manage` can CRUD their own institute's roles
 * and attach any permission via the grouped checklist. Global roles
 * (institute_id NULL) are shown read-only — they cannot be edited or
 * deleted from a tenant. Roles holding members cannot be deleted.
 */
class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.manage', only: [
                'index', 'create', 'store', 'edit', 'update', 'destroy',
            ]),
        ];
    }

    public function index(Request $request): View
    {
        $instituteId = $this->instituteId();

        $roles = Role::query()
            ->where(function ($query) use ($instituteId) {
                $query->where('institute_id', $instituteId)
                    ->orWhereNull('institute_id');
            })
            ->where('slug', '!=', 'institute-owner')
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(function ($role) use ($instituteId) {
                $role->managed = $role->institute_id !== null && (int) $role->institute_id === $instituteId;
                $role->member_count = $this->memberCount($role);
                return $role;
            });

        return view('staff.roles.index', compact('roles'));
    }

    public function create(): View
    {
        $role = new Role(['status' => 'active', 'is_system' => false]);
        $groupedPermissions = $this->groupedPermissions();
        $assigned = [];

        return view('staff.roles.create', compact('role', 'groupedPermissions', 'assigned'));
    }

    public function store(Request $request): RedirectResponse
    {
        $instituteId = $this->instituteId();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('roles', 'name')->where('institute_id', $instituteId),
            ],
            'slug' => [
                'nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles', 'slug')->where('institute_id', $instituteId),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'slug')],
        ]);

        $slug = $data['slug'] ? Str::slug($data['slug']) : Str::slug($data['name']);
        abort_if($slug === 'institute-owner', 422, 'This slug is reserved.');

        $role = Role::create([
            'institute_id' => $instituteId,
            'name' => $data['name'],
            'slug' => $slug,
            'is_system' => false,
            'status' => $data['status'],
            'created_at' => now(),
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('staff.roles.index')
            ->with('status', "Role '{$role->name}' created successfully!");
    }

    public function edit(Role $role): View
    {
        $this->ensureManaged($role);

        $groupedPermissions = $this->groupedPermissions();
        $assigned = $role->permissions()->pluck('permissions.slug')->all();

        return view('staff.roles.edit', compact('role', 'groupedPermissions', 'assigned'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->ensureManaged($role);
        $instituteId = $this->instituteId();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('roles', 'name')->where('institute_id', $instituteId)->ignore($role->id),
            ],
            'slug' => [
                'nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles', 'slug')->where('institute_id', $instituteId)->ignore($role->id),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'slug')],
        ]);

        $slug = $data['slug'] ? Str::slug($data['slug']) : Str::slug($data['name']);
        abort_if($slug === 'institute-owner', 422, 'This slug is reserved.');

        $role->update([
            'name' => $data['name'],
            'slug' => $slug,
            'status' => $data['status'],
        ]);

        $this->syncPermissions($role, $data['permissions'] ?? []);

        return redirect()->route('staff.roles.index')
            ->with('status', "Role '{$role->name}' updated successfully!");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->ensureManaged($role);

        $count = $this->memberCount($role);
        if ($count > 0) {
            return redirect()->route('staff.roles.index')
                ->with('status', "Cannot delete '{$role->name}': {$count} member(s) still assigned to this role!");
        }

        $name = $role->name;
        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('staff.roles.index')
            ->with('status', "Role '{$name}' deleted successfully!");
    }

    /**
     * All permissions grouped by module for the checklist.
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    protected function groupedPermissions(): array
    {
        return Permission::orderBy('module')->orderBy('name')
            ->get()
            ->groupBy('module')
            ->all();
    }

    protected function syncPermissions(Role $role, array $slugs): void
    {
        if (empty($slugs)) {
            $role->permissions()->detach();
            return;
        }

        $ids = Permission::whereIn('slug', array_unique($slugs))->pluck('id')->all();
        $role->permissions()->sync($ids);
    }

    /**
     * Members holding this role: institute_users rows plus web-guard
     * memberships (institution_user table) inside this institute.
     */
    protected function memberCount(Role $role): int
    {
        $instituteId = $role->institute_id;

        $staffCount = InstituteUser::where('role_id', $role->id)
            ->when($instituteId !== null, fn ($q) => $q->where('institute_id', $instituteId))
            ->count();

        $memberCount = Membership::where('role_id', $role->id)
            ->when($instituteId !== null, fn ($q) => $q->where('institution_id', $instituteId))
            ->count();

        return $staffCount + $memberCount;
    }

    /**
     * Abort unless the role is manageable from the current institute:
     * institute-owned (same institute), never global, never institute-owner.
     */
    protected function ensureManaged(Role $role): void
    {
        $instituteId = $this->instituteId();

        abort_if($role->slug === 'institute-owner', 403);
        abort_if(
            $role->institute_id === null || (int) $role->institute_id !== $instituteId,
            403,
            'You do not have permission to manage this role.'
        );
    }

    protected function instituteId(): int
    {
        $user = request()->user();

        if ($user instanceof InstituteUser) {
            abort_if(! $user->institute_id, 403, 'Institute context not found.');
            return (int) $user->institute_id;
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();
            abort_if(! $membership, 403, 'Institute context not found.');
            return (int) $membership->institution_id;
        }

        abort(403, 'Institute context not found.');
    }
}
