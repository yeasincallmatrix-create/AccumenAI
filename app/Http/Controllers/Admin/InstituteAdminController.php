<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeUnit;
use App\Models\Certificate;
use App\Models\HrEmployee;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\Notification;
use App\Models\SubscriptionPackage;
use App\Support\IndustryRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Support\PasswordHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstituteAdminController extends Controller
{
    public const INSTITUTES_COLUMNS = [
        'serial', 'institute', 'owner', 'package', 'students',
        'subscription', 'status', 'action',
    ];

    public const PER_PAGE_OPTIONS = [25, 50, 75, 100, 200, 500];

    public function index(Request $request): View
    {
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $query = Institute::query()
            ->whereNull('deleted_at')
            ->with(['package', 'users.role'])
            ->withCount('students')
            ->when($request->query('q'), fn ($query, $term) => $query
                ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhere('institute_code', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")))
            ->when($request->query('industry'), fn ($query, $industry) => $query->where('industry', $industry))
            ->when($request->query('sub_industry'), fn ($query, $subIndustry) => $query->where('sub_industry', $subIndustry))
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status));

        $items = (clone $query)->orderByDesc('id')->paginate($perPage)->withQueryString();

        $allItems = (clone $query)->orderByDesc('id')->get();

        $visibleColumns = $request->user()->preference('institutes_columns', self::INSTITUTES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::INSTITUTES_COLUMNS, (array) $visibleColumns));

        return view('admin.institutes.index', [
            'items' => $items,
            'allItems' => $allItems,
            'visibleColumns' => $visibleColumns,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'selectedIndustry' => $request->query('industry'),
            'industries' => IndustryRules::industries(null),
            'subIndustries' => is_string($request->query('industry'))
                ? IndustryRules::subIndustries('', $request->query('industry'))
                : [],
            'filters' => [
                'q' => $request->query('q'),
                'industry' => $request->query('industry'),
                'sub_industry' => $request->query('sub_industry'),
                'status' => $request->query('status'),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function saveColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);

        $columns = array_values(array_intersect(self::INSTITUTES_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('institutes_columns', $columns);

        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function saveBinColumns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string'],
        ]);
        $columns = array_values(array_intersect(self::INSTITUTES_COLUMNS, $data['columns'] ?? []));
        $request->user()->setPreference('institutes_bin_columns', $columns);
        return response()->json(['ok' => true, 'columns' => $columns]);
    }

    public function updateCertificateApprovalMode(Request $request, Institute $institute): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'certificate_approval_mode' => ['required', 'string', Rule::in([InstituteSetting::CERTIFICATE_APPROVAL_ADMIN, InstituteSetting::CERTIFICATE_APPROVAL_SUPER_ADMIN])],
        ]);

        $service = app(\App\Services\CertificateApprovalModeService::class);
        $previousMode = $service->getMode($institute->id);
        $newMode = $data['certificate_approval_mode'];

        if ($previousMode === $newMode) {
            $label = $newMode === InstituteSetting::CERTIFICATE_APPROVAL_ADMIN ? 'Admin Controlled' : 'Super Admin Required';
            $msg = "Certificate approval mode is already set to {$label}.";
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => $msg, 'data' => ['mode' => $newMode]]);
            }
            return back()->with('status', $msg);
        }

        $service->setMode($institute->id, $newMode);

        \App\Models\AuditLog::create([
            'institute_id' => $institute->id,
            'user_type' => 'platform_admin',
            'user_id' => $request->user()->id ?? auth('platform_admin')->id(),
            'action' => 'certificate_approval_mode_changed',
            'module' => 'settings',
            'record_id' => $institute->id,
            'old_values' => json_encode(['certificate_approval_mode' => $previousMode]),
            'new_values' => json_encode(['certificate_approval_mode' => $newMode]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        try {
            \App\Models\PlatformAuditLog::record('institutes', 'institute.' . $institute->id, 'certificate_approval_mode_changed', [
                'institute_id' => $institute->id,
                'from' => $previousMode,
                'to' => $newMode,
            ]);
        } catch (\Throwable $e) {}

        $label = $newMode === InstituteSetting::CERTIFICATE_APPROVAL_ADMIN ? 'Admin Controlled' : 'Super Admin Required';
        $msg = "Certificate approval mode updated to {$label}.";

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg, 'data' => ['mode' => $newMode]]);
        }

        return back()->with('status', $msg);
    }

    public function show(Institute $institute): View
    {
        $institute->load(['package', 'memberships.user', 'memberships.role', 'users.role', 'settings']);

        // Global accounts via memberships (new architecture).
        $members = $institute->memberships
            ->filter(fn ($membership) => $membership->status === 'active')
            ->map(function (Membership $membership) {
                $row = new \stdClass;
                $row->id = $membership->id;
                $row->kind = 'membership';
                $row->name = $membership->user?->name ?? '—';
                $row->role = $membership->role;
                $row->email = $membership->user?->email ?? '';
                $row->status = $membership->status;
                $row->user_id = $membership->user_id;
                $row->membership_id = $membership->id;
                $row->user_type = 'user';

                return $row;
            });

        // Legacy per-institute accounts (kept during the transition).
        $legacy = $institute->users->map(function (InstituteUser $user) {
            $row = new \stdClass;
            $row->id = $user->id;
            $row->kind = 'legacy';
            $row->name = $user->name ?? $user->email ?? '—';
            $row->role = $user->role;
            $row->email = $user->email ?? '';
            $row->status = $user->status ?? 'active';
            $row->user_id = $user->id;
            $row->membership_id = null;
            $row->user_type = 'institute_user';

            return $row;
        });

        $owner = $members->first(fn ($row) => $row->role?->slug === 'institute-owner')
            ?? $legacy->first(fn ($row) => $row->role?->slug === 'institute-owner');

        $staff = $members
            ->reject(fn ($row) => $row->role?->slug === 'institute-owner')
            ->concat($legacy->reject(fn ($row) => $row->role?->slug === 'institute-owner'))
            ->take(20);

        $hrEmployees = HrEmployee::where('institute_id', $institute->id)
            ->with(['department', 'designation'])
            ->orderByDesc('id')
            ->take(20)
            ->get();

        return view('admin.institutes.show', [
            'institute' => $institute,
            'owner' => $owner,
            'staff' => $staff,
            'hrEmployees' => $hrEmployees,
        ]);
    }

    /**
     * Delete a staff / employee from the institute (admin panel).
     * Supports membership (institution_user), legacy institute_users, and hr_employees.
     */
    public function destroyStaff(Request $request, Institute $institute, string $kind, int $id): RedirectResponse
    {
        if (! in_array($kind, ['membership', 'legacy', 'hr'], true)) {
            abort(404);
        }

        if ($kind === 'membership') {
            $membership = Membership::where('institution_id', $institute->id)->where('id', $id)->firstOrFail();
            $membership->loadMissing('role');
            if ($membership->role?->slug === 'institute-owner') {
                return back()->withErrors(['staff' => 'Cannot delete the institute owner.']);
            }
            $membership->delete();

            return back()->with('status', 'Employee removed from institute.');
        }

        if ($kind === 'legacy') {
            $user = InstituteUser::where('institute_id', $institute->id)->where('id', $id)->firstOrFail();
            $user->loadMissing('role');
            if ($user->role?->slug === 'institute-owner') {
                return back()->withErrors(['staff' => 'Cannot delete the institute owner.']);
            }
            $user->update(['status' => 'inactive']);
            $user->delete();

            return back()->with('status', 'Employee removed from institute.');
        }

        // hr
        $employee = HrEmployee::where('institute_id', $institute->id)->where('id', $id)->firstOrFail();
        $employee->delete();

        return back()->with('status', 'Employee deleted.');
    }

    public function edit(Institute $institute): View
    {
        $countryId = $institute->country_id;
        $geoLevels = $countryId
            ? AdministrativeUnit::where('country_id', $countryId)->where('status', true)
                ->join('administrative_levels', 'administrative_levels.id', '=', 'administrative_units.administrative_level_id')
                ->select('administrative_units.*', 'administrative_levels.level_number')
                ->get()->groupBy('level_number')
            : collect();

        return view('admin.institutes.edit', [
            'institute' => $institute,
            'packages' => SubscriptionPackage::orderBy('name')->get(),
            'statuses' => ['pending', 'active', 'suspended', 'expired'],
            'industries' => IndustryRules::industries($institute->country),
            'subIndustries' => $institute->industry !== null
                ? IndustryRules::subIndustries($institute->country ?? '', $institute->industry)
                : [],
            'divisions' => $geoLevels->get(1, collect()),
            'districts' => $geoLevels->get(2, collect()),
            'upazilas' => $geoLevels->get(3, collect()),
        ]);
    }

    public function update(Request $request, Institute $institute): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:80', Rule::unique('institutes', 'slug')->ignore($institute->id)],
            'institute_code' => ['nullable', 'string', 'max:20', Rule::unique('institutes', 'institute_code')->ignore($institute->id)],
            'status' => ['required', Rule::in(['pending', 'active', 'suspended', 'expired'])],
            'verified' => ['boolean'],
            'package_id' => ['nullable', 'exists:subscription_packages,id'],
            'subscription_expiry' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{4,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'admin_level_1_id' => ['nullable', 'integer', 'exists:administrative_units,id'],
            'admin_level_2_id' => ['nullable', 'integer', 'exists:administrative_units,id'],
            'admin_level_3_id' => ['nullable', 'integer', 'exists:administrative_units,id'],
            'founded_year' => ['nullable', 'integer', 'between:1950,2100'],
            'country' => ['nullable', 'string', 'max:80', Rule::in(array_keys(config('countries', [])))],
            'industry' => ['nullable', 'string', 'max:60', Rule::in(array_keys(IndustryRules::industries($institute->country)))],
            'description' => ['nullable', 'string'],
            'ai_enabled' => ['boolean'],
            'ai_features' => ['nullable', 'array'],
            'ai_features.*' => ['in:assistant,analytics,content,reports,automation'],
            'ai_daily_limit' => ['nullable', 'integer', 'min:0'],
            'ai_monthly_limit' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['verified'] = $request->boolean('verified');
        $institute->update(Arr::except($data, ['ai_enabled', 'ai_features', 'ai_daily_limit', 'ai_monthly_limit']));

        InstituteSetting::withoutGlobalScopes()->updateOrCreate(
            ['institute_id' => $institute->id],
            [
                'ai_config' => [
                    'enabled' => $request->boolean('ai_enabled'),
                    'features' => array_values((array) $request->input('ai_features', [])),
                    'daily_limit' => (int) ($request->input('ai_daily_limit') ?? 0),
                    'monthly_limit' => (int) ($request->input('ai_monthly_limit') ?? 0),
                ],
            ]
        );

        return redirect()
            ->route('admin.institutes.show', $institute)
            ->with('status', 'Institute updated.');
    }

    public function action(Request $request, Institute $institute): RedirectResponse|JsonResponse
    {
        $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'suspend', 'reactivate', 'delete'])]]);

        $action = $request->input('action');

        if ($action === 'delete') {
            return $this->deleteInstitute($request, $institute);
        }

        $map = [
            'approve' => 'active',
            'reactivate' => 'active',
            'suspend' => 'suspended',
            'reject' => 'cancelled',
        ];

        $targetStatus = $map[$action];

        // Idempotent handling — prevent duplicate approval / redundant transitions
        if ($institute->status === $targetStatus) {
            $dupMessage = [
                'approve' => 'Institute is already approved.',
                'reactivate' => 'Institute is already active.',
                'suspend' => 'Institute is already suspended.',
                'reject' => 'Institute is already rejected.',
            ][$action];

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $dupMessage,
                    'data' => ['id' => $institute->id, 'status' => $institute->status],
                ]);
            }

            return redirect()->route('admin.institutes.index')->with('status', $dupMessage);
        }

        // Guard: approve only from pending (or cancelled→active is reactivate, not approve)
        if ($action === 'approve' && $institute->status !== 'pending') {
            $msg = 'Only pending institutes can be approved.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['action' => $msg]);
        }

        $institute->update([
            'status' => $targetStatus,
            'onboarded_at' => $action === 'approve' ? now() : $institute->onboarded_at,
        ]);

        $message = [
            'approve' => 'Institute approved.',
            'reactivate' => 'Institute reactivated.',
            'suspend' => 'Institute suspended.',
            'reject' => 'Institute rejected.',
        ][$action];

        $this->notifyInstitute($institute->id, 'institute_registration', 'Institute status changed', $message);

        // Audit log — reuse existing PlatformAuditLog (no secrets)
        try {
            \App\Models\PlatformAuditLog::record('institutes', 'institute.' . $institute->id, $action, [
                'institute_id' => $institute->id,
                'institute_name' => $institute->name,
                'from_status' => $institute->getOriginal('status'),
                'to_status' => $targetStatus,
            ]);
        } catch (\Throwable $e) {
            // Audit failure must not block business action
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['id' => $institute->id, 'status' => $targetStatus],
            ]);
        }

        return redirect()
            ->route('admin.institutes.index')
            ->with('status', $message);
    }

    protected function deleteInstitute(Request $request, Institute $institute): RedirectResponse|JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $admin = $request->user();
        $plainPassword = trim((string) $request->input('password'));
        if ($plainPassword === '' || ! PasswordHash::safeCheck($plainPassword, (string) $admin->getAuthPassword())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your password is incorrect.',
                    'errors' => ['password' => ['Your password is incorrect.']],
                ], 422);
            }

            return back()->withErrors(['password' => 'Your password is incorrect.']);
        }

        // Prevent double-delete
        if ($institute->deleted_at !== null) {
            $msg = 'Institute is already in the recycle bin.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['institute' => $msg]);
        }

        // Use SoftDeletes trait properly: set status/deleted_by then soft-delete
        $institute->update([
            'status' => 'cancelled',
            'deleted_by' => $admin->id,
        ]);
        $institute->delete();

        // LEGACY: soft-delete per-institute accounts
        InstituteUser::query()
            ->where('institute_id', $institute->id)
            ->update(['status' => 'inactive', 'deleted_at' => now()]);

        // MEMBERSHIP (institution_user): tenant-scoped membership must be soft-deleted
        // with the business, but the global User account MUST survive (multi-business safety).
        // Membership has SoftDeletes and FK `institution_id -> institutes ON DELETE CASCADE`,
        // so forceDelete will cascade membership hard-delete; here we soft-delete only.
        Membership::where('institution_id', $institute->id)->delete();

        try {
            \App\Models\PlatformAuditLog::record('institutes', 'institute.' . $institute->id, 'deleted', [
                'institute_id' => $institute->id,
                'institute_name' => $institute->name,
            ]);
        } catch (\Throwable $e) {
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Institute moved to recycle bin.',
                'data' => ['id' => $institute->id],
            ]);
        }

        return redirect()
            ->route('admin.institutes.index')
            ->with('status', 'Institute moved to recycle bin.');
    }

    public function bin(Request $request): View
    {
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $baseInstitutes = Institute::onlyTrashed()
            ->with(['package', 'users.role', 'memberships.user', 'memberships.role'])
            ->withCount('students')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($qq) => $qq->where('name', 'like', "%{$term}%")
                ->orWhere('slug', 'like', "%{$term}%")
                ->orWhere('institute_code', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($request->query('industry'), fn ($q, $industry) => $q->where('industry', $industry))
            ->when($request->query('sub_industry'), fn ($q, $sub) => $q->where('sub_industry', $sub))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        $institutes = (clone $baseInstitutes)->orderByDesc('deleted_at')->paginate($perPage)->withQueryString();
        $allInstitutes = (clone $baseInstitutes)->orderByDesc('deleted_at')->get();

        // E24: enrich each institute with owner display + other-business count for safety UI
        // Owner is resolved via membership with institute-owner role, fallback to legacy users.
        $enrich = function ($collection) {
            foreach ($collection as $inst) {
                $ownerMembership = $inst->memberships->first(fn ($m) => $m->role?->slug === 'institute-owner');
                $ownerUser = $ownerMembership?->user;
                if ($ownerUser) {
                    $inst->setAttribute('_e24_owner_name', $ownerUser->name ?? $ownerUser->email ?? '—');
                    $inst->setAttribute('_e24_owner_email', $ownerUser->email ?? '');
                    // Other businesses: count active + trashed memberships excluding current institute
                    try {
                        $otherCount = Membership::withTrashed()->where('user_id', $ownerUser->id)->where('institution_id', '!=', $inst->id)->count();
                        // Also count non-deleted institutes where user still has active row
                        $activeOther = Membership::where('user_id', $ownerUser->id)->where('institution_id', '!=', $inst->id)->count();
                        $inst->setAttribute('_e24_other_businesses', $otherCount);
                        $inst->setAttribute('_e24_other_active', $activeOther);
                    } catch (\Throwable $e) {
                        $inst->setAttribute('_e24_other_businesses', 0);
                        $inst->setAttribute('_e24_other_active', 0);
                    }
                } else {
                    $legacyOwner = $inst->users->first(fn ($u) => $u->role?->slug === 'institute-owner');
                    $inst->setAttribute('_e24_owner_name', $legacyOwner?->name ?? $legacyOwner?->email ?? '—');
                    $inst->setAttribute('_e24_owner_email', $legacyOwner?->email ?? '');
                    $inst->setAttribute('_e24_other_businesses', 0);
                    $inst->setAttribute('_e24_other_active', 0);
                }
            }
        };
        $enrich($institutes);
        $enrich($allInstitutes);

        // Certificates still paginated separately (keep 20 or perPage)
        $certificates = Certificate::query()
            ->onlyTrashed()
            ->with(['student', 'course', 'batch', 'institute'])
            ->orderByDesc('deleted_at')
            ->paginate(20)->withQueryString();

        $visibleColumns = $request->user()->preference('institutes_bin_columns', self::INSTITUTES_COLUMNS);
        $visibleColumns = array_values(array_intersect(self::INSTITUTES_COLUMNS, (array) $visibleColumns));
        // Ensure bin still respects at least serial/institute/action
        if (empty($visibleColumns)) { $visibleColumns = self::INSTITUTES_COLUMNS; }

        return view('admin.institutes.bin', [
            'institutes' => $institutes,
            'allInstitutes' => $allInstitutes,
            'certificates' => $certificates,
            'visibleColumns' => $visibleColumns,
            'perPage' => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'industries' => IndustryRules::industries(null),
            'subIndustries' => is_string($request->query('industry')) ? IndustryRules::subIndustries('', $request->query('industry')) : [],
            'filters' => [
                'q' => $request->query('q'),
                'industry' => $request->query('industry'),
                'sub_industry' => $request->query('sub_industry'),
                'status' => $request->query('status'),
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, Institute $institute): RedirectResponse|JsonResponse
    {
        if ($institute->deleted_at === null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Institute is not in the recycle bin.',
                ], 422);
            }

            return redirect()->route('admin.institutes.bin')->with('status', 'Institute is not in the recycle bin.');
        }

        // Use SoftDeletes restore, then update status
        $institute->restore();
        $institute->update(['deleted_by' => null, 'status' => 'active']);

        InstituteUser::withTrashed()->where('institute_id', $institute->id)
            ->update(['deleted_at' => null, 'status' => 'active']);

        // Restore memberships that were soft-deleted with the institute.
        // Membership::restore() respects SoftDeletes and does not touch the global users table.
        Membership::withTrashed()->where('institution_id', $institute->id)->restore();
        // Ensure restored memberships are active (owner quirk: keep original status if suspended)
        Membership::withTrashed()->where('institution_id', $institute->id)
            ->where('status', 'inactive')
            ->update(['status' => 'active']);

        $this->notifyInstitute($institute->id, 'institute_registration', 'Institute restored', 'Your institute has been restored.');

        try {
            \App\Models\PlatformAuditLog::record('institutes', 'institute.' . $institute->id, 'restored', [
                'institute_id' => $institute->id,
                'institute_name' => $institute->name,
            ]);
        } catch (\Throwable $e) {
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Institute restored.',
            ]);
        }

        return redirect()
            ->route('admin.institutes.bin')
            ->with('status', 'Institute restored.');
    }

    public function forceDelete(Request $request, Institute $institute): RedirectResponse|JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $admin = $request->user();
        $plainPassword = trim((string) $request->input('password'));
        if ($plainPassword === '' || ! PasswordHash::safeCheck($plainPassword, (string) $admin->getAuthPassword())) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your password is incorrect.',
                    'errors' => ['password' => ['Your password is incorrect.']],
                ], 422);
            }

            return back()->withErrors(['password' => 'Your password is incorrect.']);
        }

        // Production-safe: never disable FK constraints.
        // Only allow force-delete if institute is already soft-deleted (in recycle bin).
        if ($institute->deleted_at === null) {
            $msg = 'Institute must be in the recycle bin before permanent deletion.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['institute' => $msg]);
        }

        // Preserve audit: log before hard delete
        $instituteName = $institute->name;
        $instituteId = $institute->id;

        try {
            \App\Models\PlatformAuditLog::record('institutes', 'institute.' . $instituteId, 'force_deleted', [
                'institute_id' => $instituteId,
                'institute_name' => $instituteName,
            ]);
        } catch (\Throwable $e) {
        }

        // E24 SAFETY: capture User IDs that have membership in this institute
        // before hard delete, to assert they survive (multi-business isolation).
        $membershipUserIds = Membership::withTrashed()->where('institution_id', $institute->id)->pluck('user_id')->unique()->values();

        DB::transaction(function () use ($institute) {
            // Remove pivot rows that would block FK; remaining tenant tables have
            // cascadeOnDelete so forceDelete will cascade, but we do not disable FK.
            // instituteCourses is the only explicit pivot we clean; others cascade or remain.
            // Membership and InstituteUser rows cascade via DB FK ON DELETE CASCADE
            // — they are tenant-scoped and safe to cascade; User (global) never cascades.
            $institute->instituteCourses()->delete();
            $institute->forceDelete();
        });

        // SAFETY VERIFICATION: global User accounts must never be automatically deleted
        // by business permanent delete. The ON DELETE CASCADE is on institution_user.institution_id
        // (membership deleted) and NOT on users.id. Verify surviving users still exist.
        // No corrective action needed — this is a post-condition check; log anomaly if missing.
        if ($membershipUserIds->isNotEmpty()) {
            try {
                $surviving = \App\Models\User::withTrashed()->whereIn('id', $membershipUserIds)->count();
                if ($surviving !== $membershipUserIds->count()) {
                    \Illuminate\Support\Facades\Log::warning('E24_forceDelete_user_integrity_anomaly', [
                        'institute_id' => $instituteId,
                        'expected_users' => $membershipUserIds->count(),
                        'surviving_users' => $surviving,
                    ]);
                }
            } catch (\Throwable $e) {}
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Institute permanently deleted.',
            ]);
        }

        return redirect()
            ->route('admin.institutes.bin')
            ->with('status', 'Institute permanently deleted.');
    }

    public function batchAction(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:2'],
            'ids.*' => ['integer', 'exists:institutes,id'],
            'action' => ['required', Rule::in(['approve', 'delete'])],
            'password' => [Rule::requiredIf(fn () => $request->input('action') === 'delete'), 'nullable', 'string'],
        ]);

        $ids = array_map('intval', $data['ids']);
        $action = $data['action'];

        if ($action === 'delete') {
            $admin = $request->user();
            $plainPassword = trim((string) ($data['password'] ?? ''));
            if ($plainPassword === '' || ! PasswordHash::safeCheck($plainPassword, (string) $admin->getAuthPassword())) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your password is incorrect.',
                        'errors' => ['password' => ['Your password is incorrect.']],
                    ], 422);
                }
                return back()->withErrors(['password' => 'Your password is incorrect.']);
            }
        }

        $institutes = Institute::whereIn('id', $ids)->get();
        $approved = 0;
        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($institutes, $action, $request, &$approved, &$deleted, &$skipped) {
            foreach ($institutes as $institute) {
                if ($institute->deleted_at !== null) {
                    $skipped++;
                    continue;
                }
                if ($action === 'approve') {
                    if ($institute->status !== 'pending') {
                        $skipped++;
                        continue;
                    }
                    $institute->update(['status' => 'active', 'onboarded_at' => now()]);
                    $this->notifyInstitute($institute->id, 'institute_registration', 'Institute approved', 'Your institute has been approved.');
                    try {
                        \App\Models\PlatformAuditLog::record('institutes', 'institute.' . $institute->id, 'batch_approve', [
                            'institute_id' => $institute->id,
                            'institute_name' => $institute->name,
                        ]);
                    } catch (\Throwable $e) {}
                    $approved++;
                } elseif ($action === 'delete') {
                    $institute->update(['status' => 'cancelled', 'deleted_by' => $request->user()->id]);
                    $institute->delete();
                    InstituteUser::query()->where('institute_id', $institute->id)->update(['status' => 'inactive', 'deleted_at' => now()]);
                    Membership::where('institution_id', $institute->id)->delete();
                    try {
                        \App\Models\PlatformAuditLog::record('institutes', 'institute.' . $institute->id, 'batch_deleted', [
                            'institute_id' => $institute->id,
                            'institute_name' => $institute->name,
                        ]);
                    } catch (\Throwable $e) {}
                    $deleted++;
                }
            }
        });

        $message = $action === 'approve'
            ? "{$approved} institute(s) approved." . ($skipped ? " {$skipped} skipped (not pending)." : '')
            : "{$deleted} institute(s) moved to recycle bin." . ($skipped ? " {$skipped} skipped." : '');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'data' => ['approved' => $approved, 'deleted' => $deleted, 'skipped' => $skipped]]);
        }
        return redirect()->route('admin.institutes.index')->with('status', $message);
    }

    public function batchBinAction(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:2'],
            'ids.*' => ['integer', 'exists:institutes,id'],
            'action' => ['required', Rule::in(['restore', 'forceDelete'])],
            'password' => [Rule::requiredIf(fn () => $request->input('action') === 'forceDelete'), 'nullable', 'string'],
        ]);

        $ids = array_map('intval', $data['ids']);
        $action = $data['action'];

        if ($action === 'forceDelete') {
            $admin = $request->user();
            $plainPassword = trim((string) ($data['password'] ?? ''));
            if ($plainPassword === '' || ! PasswordHash::safeCheck($plainPassword, (string) $admin->getAuthPassword())) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your password is incorrect.',
                        'errors' => ['password' => ['Your password is incorrect.']],
                    ], 422);
                }
                return back()->withErrors(['password' => 'Your password is incorrect.']);
            }
        }

        $institutes = Institute::withTrashed()->whereIn('id', $ids)->get();
        $restored = 0;
        $deleted = 0;
        $skipped = 0;

        DB::transaction(function () use ($institutes, $action, $request, &$restored, &$deleted, &$skipped) {
            foreach ($institutes as $institute) {
                if ($action === 'restore') {
                    if ($institute->deleted_at === null) { $skipped++; continue; }
                    $institute->restore();
                    $institute->update(['deleted_by' => null, 'status' => 'active']);
                    InstituteUser::withTrashed()->where('institute_id', $institute->id)->update(['deleted_at' => null, 'status' => 'active']);
                    Membership::withTrashed()->where('institution_id', $institute->id)->restore();
                    Membership::withTrashed()->where('institution_id', $institute->id)->where('status', 'inactive')->update(['status' => 'active']);
                    $this->notifyInstitute($institute->id, 'institute_registration', 'Institute restored', 'Your institute has been restored.');
                    try { \App\Models\PlatformAuditLog::record('institutes', 'institute.' . $institute->id, 'batch_restored', ['institute_id' => $institute->id, 'institute_name' => $institute->name]); } catch (\Throwable $e) {}
                    $restored++;
                } elseif ($action === 'forceDelete') {
                    if ($institute->deleted_at === null) { $skipped++; continue; }
                    $institute->instituteCourses()->delete();
                    $institute->forceDelete();
                    try { \App\Models\PlatformAuditLog::record('institutes', 'institute.' . $institute->id, 'batch_force_deleted', ['institute_id' => $institute->id, 'institute_name' => $institute->name]); } catch (\Throwable $e) {}
                    $deleted++;
                }
            }
        });

        $message = $action === 'restore'
            ? "{$restored} institute(s) restored." . ($skipped ? " {$skipped} skipped." : '')
            : "{$deleted} institute(s) permanently deleted." . ($skipped ? " {$skipped} skipped (not in bin)." : '');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'data' => ['restored' => $restored, 'deleted' => $deleted, 'skipped' => $skipped]]);
        }
        return redirect()->route('admin.institutes.bin')->with('status', $message);
    }

    protected function notifyInstitute(int $instituteId, string $category, string $title, string $message): void
    {
        Notification::create([
            'scope' => 'institute',
            'institute_id' => $instituteId,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'created_by_type' => 'platform_admin',
            'created_by_id' => auth('platform_admin')->id(),
            'created_at' => now(),
        ]);
    }
}
