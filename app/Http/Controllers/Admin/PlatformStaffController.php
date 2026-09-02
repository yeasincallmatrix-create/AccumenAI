<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\PlatformAuditLog;
use App\Models\PlatformStaff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PlatformStaffController extends Controller
{
    public function index(Request $request): View
    {
        $staff = PlatformStaff::with('permissions')
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('admin.platform_staff.index', [
            'staff' => $staff,
            'roles' => PlatformStaff::ROLES,
            'permissions' => Permission::orderBy('slug')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.platform_staff.create', [
            'roles' => PlatformStaff::ROLES,
            'rolePermissions' => PlatformStaff::ROLE_PERMISSIONS,
            'permissions' => Permission::orderBy('slug')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:150', 'unique:platform_staffs,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'in:'.implode(',', PlatformStaff::ROLES)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        // Explicitly strip any escalation vectors even if validated
        unset($data['is_owner'], $data['singleton_guard'], $data['guard'], $data['super_admin']);

        $staff = PlatformStaff::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password_hash' => $data['password'],
            'role' => $data['role'],
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        if (! empty($data['permissions'])) {
            $staff->permissions()->sync($data['permissions']);
        }

        PlatformAuditLog::record('platform_staff', 'create', 'created', [
            'staff_id' => $staff->id,
            'email' => $staff->email,
            'role' => $staff->role,
        ]);

        return redirect()->route('admin.platform-staff.index')->with('status', 'Platform staff created: '.$staff->email);
    }

    public function edit(PlatformStaff $platformStaff): View
    {
        return view('admin.platform_staff.edit', [
            'staff' => $platformStaff->load('permissions'),
            'roles' => PlatformStaff::ROLES,
            'permissions' => Permission::orderBy('slug')->get(),
        ]);
    }

    public function update(Request $request, PlatformStaff $platformStaff): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:60'],
            'last_name' => ['sometimes', 'string', 'max:60'],
            'email' => ['sometimes', 'email', 'max:150', 'unique:platform_staffs,email,'.$platformStaff->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['sometimes', 'in:'.implode(',', PlatformStaff::ROLES)],
            'status' => ['sometimes', 'in:active,suspended,inactive'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        // Block mass assignment escalation
        foreach (['is_owner', 'singleton_guard', 'guard', 'super_admin', 'is_super'] as $blocked) {
            unset($data[$blocked]);
        }

        if (isset($data['email'])) $platformStaff->email = $data['email'];
        if (isset($data['first_name'])) $platformStaff->first_name = $data['first_name'];
        if (isset($data['last_name'])) $platformStaff->last_name = $data['last_name'];
        if (array_key_exists('phone', $data)) $platformStaff->phone = $data['phone'];
        if (isset($data['role'])) $platformStaff->role = $data['role'];
        if (isset($data['status'])) $platformStaff->status = $data['status'];
        $platformStaff->save();

        if (array_key_exists('permissions', $data)) {
            $platformStaff->permissions()->sync($data['permissions'] ?? []);
        }

        PlatformAuditLog::record('platform_staff', 'update', 'updated', [
            'staff_id' => $platformStaff->id,
            'role' => $platformStaff->role,
        ]);

        return redirect()->route('admin.platform-staff.index')->with('status', 'Platform staff updated.');
    }

    public function destroy(PlatformStaff $platformStaff): RedirectResponse
    {
        $email = $platformStaff->email;
        $platformStaff->delete();

        PlatformAuditLog::record('platform_staff', 'delete', 'deleted', ['email' => $email]);

        return redirect()->route('admin.platform-staff.index')->with('status', 'Platform staff disabled: '.$email);
    }

    public function toggleStatus(PlatformStaff $platformStaff): RedirectResponse
    {
        $new = $platformStaff->status === 'active' ? 'suspended' : 'active';
        $platformStaff->update(['status' => $new]);

        PlatformAuditLog::record('platform_staff', 'status', 'toggled', ['email' => $platformStaff->email, 'status' => $new]);

        return back()->with('status', 'Staff status updated to '.$new);
    }
}
