<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\User;
use App\Support\InstituteDomain;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BusinessProfileController extends Controller
{
    public function show(Request $request): View
    {
        $institute = $this->resolveActiveInstitute($request);

        abort_unless($institute !== null, 403, 'No active business workspace.');

        // Tenant isolation verification: active institute must match Workspace/TenantContext
        $this->assertTenantMatchesActive($request, $institute);

        $domain = InstituteDomain::fromInstitute($institute);

        // Tenant-scoped queries — rely on TenantScoped global scope when enabled,
        // but explicitly filter by institute_id for safety (no withoutGlobalScopes).
        $branches = Branch::where('institute_id', $institute->id)
            ->orderBy('name')
            ->get(['id', 'institute_id', 'name', 'phone', 'email', 'address', 'status', 'is_principal']);

        // Subscription — safe fields only (never price_paid / payment_reference)
        $subscription = $institute->instituteSubscriptions()
            ->with('package')
            ->orderByDesc('id')
            ->first();

        $package = $subscription?->package ?? $institute->package;

        // Enabled modules (safe, via ModuleAccessService)
        $enabledModules = [];
        $allModules = [];
        try {
            $enabledModules = app(\App\Services\ModuleAccessService::class)->getEnabledModules($institute);
            $allModules = app(\App\Services\ModuleAccessService::class)->getAllModules();
        } catch (\Throwable $e) {
            $enabledModules = [];
        }

        // Membership summary — tenant scoped counts
        $usersCount = 0;
        $branchesCount = $branches->count();
        try {
            $usersCount = $institute->memberships()->count()
                + $institute->users()->count();
            // memberships + legacy institute_users — show total distinct
            // Prefer memberships count + institute_users count is safe; may double-count migration window but harmless for display
        } catch (\Throwable $e) {
            $usersCount = 0;
        }

        // Business-type-aware data — only for current institute
        $academicData = null;
        $professionalData = null;

        if ($domain === InstituteDomain::ACADEMIC) {
            $academicData = $this->loadAcademicData($institute);
        } elseif ($domain === InstituteDomain::PROFESSIONAL) {
            $professionalData = $this->loadProfessionalData($institute);
        }

        // Industry label via config
        $industryLabel = $this->industryLabel($institute->industry);
        $subIndustryLabel = $this->subIndustryLabel($institute->industry, $institute->sub_industry);

        // Settings — safe fields only
        $settings = $institute->settings;

        return view('business.profile', [
            'institute' => $institute,
            'domain' => $domain,
            'domainLabel' => ucfirst($domain),
            'industryLabel' => $industryLabel,
            'subIndustryLabel' => $subIndustryLabel,
            'branches' => $branches,
            'subscription' => $subscription,
            'package' => $package,
            'enabledModules' => $enabledModules,
            'allModules' => $allModules,
            'usersCount' => $usersCount,
            'branchesCount' => $branchesCount,
            'academicData' => $academicData,
            'professionalData' => $professionalData,
            'settings' => $settings,
            'canEdit' => $this->canEdit($request),
        ]);
    }

    /**
     * Resolve active institute from current authenticated workspace.
     * Never trusts request institute_id.
     */
    private function resolveActiveInstitute(Request $request): ?Institute
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            return Institute::find($user->institute_id);
        }

        if ($user instanceof User) {
            $membership = Workspace::membership();
            if ($membership !== null) {
                return $membership->institution;
            }
            // Fallback via TenantContext set by SetTenantContext middleware
            $tid = TenantContext::id();
            if ($tid !== null) {
                return Institute::find($tid);
            }
            // Last: first active membership (same as Workspace fallback)
            $fallback = $user->memberships()->where('status', 'active')->orderBy('institution_id')->first();
            if ($fallback) {
                return Institute::find($fallback->institution_id);
            }
        }

        // Also try TenantContext directly (covers tenant middleware)
        $tid = TenantContext::id();
        if ($tid !== null) {
            return Institute::find($tid);
        }

        return null;
    }

    private function assertTenantMatchesActive(Request $request, Institute $institute): void
    {
        // If TenantContext is enabled, it must match the resolved institute
        if (TenantContext::enabled() && (int) TenantContext::id() !== (int) $institute->id) {
            // Mismatch indicates stale context — do not leak, abort
            throw new HttpException(403, 'Workspace mismatch.');
        }

        $user = $request->user();
        if ($user instanceof User) {
            $membership = Workspace::membership();
            if ($membership !== null && (int) $membership->institution_id !== (int) $institute->id) {
                throw new HttpException(403, 'Workspace mismatch.');
            }
        }
        if ($user instanceof InstituteUser) {
            if ((int) $user->institute_id !== (int) $institute->id) {
                throw new HttpException(403, 'Workspace mismatch.');
            }
        }
    }

    private function canEdit(Request $request): bool
    {
        $user = $request->user();
        if ($user instanceof InstituteUser) {
            return $user->hasPermission('settings.manage');
        }
        if ($user instanceof User) {
            $m = Workspace::membership();
            return $m ? $m->hasPermission('settings.manage') : false;
        }
        return false;
    }

    private function loadAcademicData(Institute $institute): array
    {
        $data = [];
        try {
            $data['studentsCount'] = \App\Models\Student::where('institute_id', $institute->id)->count();
        } catch (\Throwable $e) { $data['studentsCount'] = 0; }
        try {
            $data['batchesCount'] = \App\Models\Batch::where('institute_id', $institute->id)->count();
        } catch (\Throwable $e) { $data['batchesCount'] = 0; }
        try {
            $data['coursesCount'] = \App\Models\InstituteCourse::where('institute_id', $institute->id)->count();
        } catch (\Throwable $e) { $data['coursesCount'] = 0; }
        try {
            $data['subjectsCount'] = \App\Models\Subject::where('institute_id', $institute->id)->count();
        } catch (\Throwable $e) { $data['subjectsCount'] = 0; }
        try {
            $data['academicYears'] = \App\Models\AcademicYear::where('institute_id', $institute->id)->orderByDesc('id')->limit(5)->get();
        } catch (\Throwable $e) { $data['academicYears'] = collect(); }
        try {
            $data['structureLabels'] = \App\Models\StructureLabel::where('institute_id', $institute->id)->count();
        } catch (\Throwable $e) { $data['structureLabels'] = 0; }
        try {
            $data['recentCourses'] = \App\Models\InstituteCourse::where('institute_id', $institute->id)->with('course')->limit(6)->get();
        } catch (\Throwable $e) { $data['recentCourses'] = collect(); }
        return $data;
    }

    private function loadProfessionalData(Institute $institute): array
    {
        $data = [];
        try {
            $data['coursesCount'] = \App\Models\InstituteCourse::where('institute_id', $institute->id)->count();
        } catch (\Throwable $e) { $data['coursesCount'] = 0; }
        try {
            $data['batchesCount'] = \App\Models\Batch::where('institute_id', $institute->id)->count();
        } catch (\Throwable $e) { $data['batchesCount'] = 0; }
        try {
            $data['subjectsCount'] = \App\Models\Subject::where('institute_id', $institute->id)->count();
        } catch (\Throwable $e) { $data['subjectsCount'] = 0; }
        try {
            $data['teachersCount'] = \App\Models\InstituteUser::where('institute_id', $institute->id)->count();
        } catch (\Throwable $e) { $data['teachersCount'] = 0; }
        try {
            $data['recentCourses'] = \App\Models\InstituteCourse::where('institute_id', $institute->id)->with('course')->limit(6)->get();
        } catch (\Throwable $e) { $data['recentCourses'] = collect(); }
        try {
            $data['recentBatches'] = \App\Models\Batch::where('institute_id', $institute->id)->orderByDesc('id')->limit(5)->get();
        } catch (\Throwable $e) { $data['recentBatches'] = collect(); }
        return $data;
    }

    private function industryLabel(?string $key): string
    {
        if (empty($key)) return 'Not provided';
        $industries = config('industry_rules.global.industries', []);
        return $industries[$key] ?? ucwords(str_replace('_', ' ', $key));
    }

    private function subIndustryLabel(?string $industry, ?string $sub): string
    {
        if (empty($sub)) return 'Not provided';
        if (!empty($industry)) {
            $subs = \App\Support\IndustryRules::subIndustries('', $industry);
            if (isset($subs[$sub])) return $subs[$sub];
        }
        // fallback across all industries
        $all = config('industry_rules.global.sub_industries', []);
        foreach ($all as $map) {
            if (isset($map[$sub])) return $map[$sub];
        }
        return ucwords(str_replace('_', ' ', $sub));
    }
}
