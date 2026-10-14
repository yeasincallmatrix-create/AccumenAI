<?php

namespace App\Http\Controllers;

use App\Enums\BusinessEntityType;
use App\Models\AuditLog;
use App\Models\FiscalYear;
use App\Models\IndustrySetting;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Journal;
use App\Models\Medical\Medicine;
use App\Models\PlatformAdmin;
use App\Models\Setting;
use App\Models\Theme;
use App\Models\User;
use App\Services\Accounting\BusinessEntityService;
use App\Services\Accounting\TenantAccountingModeService;
use App\Services\Auth\PasswordService;
use App\Services\CertificateApprovalModeService;
use App\Services\Medical\DgdaService;
use App\Services\Medical\MedicalBillingJournalService;
use App\Services\ModuleAccessService;
use App\Support\PasswordPolicy;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InstituteSettingController extends Controller
{
    public function __construct(
        private readonly CertificateApprovalModeService $approvalModeService,
    ) {}

    public function verify(Request $request): View
    {
        $institute = Institute::query()
            ->with('package')
            ->find($request->user()->institute_id);

        return view('institute.verify', ['institute' => $institute]);
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $membership = $user instanceof User ? Workspace::membership() : null;

        $canManageSettings = $user instanceof InstituteUser
            ? $user->hasPermission('settings.manage')
            : ($membership?->hasPermission('settings.manage') ?? false);

        $canPromote = $user instanceof InstituteUser
            ? $user->hasPermission('promotion.manage')
            : ($membership?->hasPermission('promotion.manage') ?? false);

        $instituteId = $this->resolveInstituteId($user);

        $advancedEnabled = app(TenantAccountingModeService::class)->isAdvancedEnabled();
        $entityService = app(BusinessEntityService::class);
        $entityType = $entityService->getType((int) tenant_id());
        $entityOptions = BusinessEntityType::advancedOptions();
        $suggestedAccounts = $advancedEnabled ? $entityService->suggestedAccounts($entityType) : [];

        // Medical billing journal settings: same gates as the PUT route
        // (permission:settings.accounting.manage + module_access:finance) so
        // the form is never rendered for users who could only 403 on save.
        $canManageMedicalBilling = false;
        $medicalBillingMode = null;
        $medicalBillingAutoPost = false;
        if ($instituteId !== null) {
            $holdsBillingPermission = $user instanceof InstituteUser
                ? $user->hasPermission('settings.accounting.manage')
                : ($membership?->hasPermission('settings.accounting.manage') ?? false);
            $institute = Institute::query()->find($instituteId);
            $financeEnabled = $institute !== null
                && app(ModuleAccessService::class)->isEnabled($institute, 'finance');

            $canManageMedicalBilling = $holdsBillingPermission && $financeEnabled;

            if ($canManageMedicalBilling) {
                $journalService = app(MedicalBillingJournalService::class);
                $medicalBillingMode = $journalService->getRevenueMode((int) $instituteId);
                $medicalBillingAutoPost = $journalService->getAutoPost((int) $instituteId);
            }
        }

        return view('settings.index', [
            'canManageSettings' => $canManageSettings,
            'canPromote' => $canPromote,
            'setting' => $instituteId !== null ? $this->resolveSetting($instituteId) : null,
            'themes' => Theme::query()->where('status', 'active')->orderBy('is_default', 'desc')->orderBy('name')->get(),
            'sessions' => DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->orderByDesc('last_activity')
                ->get(),
            'currentSessionId' => $request->session()->getId(),
            'securityUser' => $user,
            'securityGuard' => $user instanceof PlatformAdmin ? 'platform_admin' : 'institute_user',
            'entityType' => $entityType,
            'entityOptions' => $entityOptions,
            'suggestedAccounts' => $suggestedAccounts,
            'advancedEnabled' => $advancedEnabled,
            'canManageMedicalBilling' => $canManageMedicalBilling,
            'medicalBillingMode' => $medicalBillingMode,
            'medicalBillingAutoPost' => $medicalBillingAutoPost,
        ]);
    }

    public function account(Request $request): View
    {
        return view('settings.account', ['user' => $request->user()]);
    }

    public function appearance(Request $request): View
    {
        return view('settings.appearance', [
            'setting' => $this->resolveSetting($this->resolveInstituteId($request->user()) ?? $request->user()->institute_id),
            'themes' => Theme::query()->where('status', 'active')->orderBy('is_default', 'desc')->orderBy('name')->get(),
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme_slug' => ['required', 'string', Rule::exists('themes', 'slug')->where('status', 'active')],
            'sidebar_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tall_navigation' => ['nullable', 'boolean'],
        ]);

        $theme = Theme::query()
            ->where('status', 'active')
            ->where('slug', $data['theme_slug'])
            ->firstOrFail();

        $data['theme'] = $theme->slug;
        $data['primary_color'] = $theme->primary_color;
        $data['secondary_color'] = $theme->secondary_color;
        $data['tall_navigation'] = $request->boolean('tall_navigation');
        unset($data['theme_slug']);

        $data = array_filter($data, fn ($value) => $value !== null && $value !== '');

        InstituteSetting::updateOrCreate(
            ['institute_id' => $this->resolveInstituteId($request->user())],
            $data
        );

        return redirect()
            ->route('settings.index', '#pane-appearance')
            ->with('status', 'Appearance settings updated.');
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'timezone'],
            'language' => ['required', 'in:bn,en'],
            'date_format' => ['required', 'in:dmy,mdy,ymd'],
        ]);

        // Graceful while the date_format migration is pending on an environment.
        if (! Schema::hasColumn('institute_settings', 'date_format')) {
            unset($data['date_format']);
        }

        InstituteSetting::updateOrCreate(
            ['institute_id' => $this->resolveInstituteId($request->user())],
            $data
        );

        return redirect()
            ->route('settings.index', '#pane-general')
            ->with('status', 'General settings updated.');
    }

    public function updateDgda(Request $request): RedirectResponse
    {
        $user = $request->user();
        $canManage = $user instanceof InstituteUser
            ? $user->hasPermission('settings.manage')
            : (Workspace::membershipFor($user)?->hasPermission('settings.manage') ?? false);
        abort_unless($canManage, 403);

        $data = $request->validate([
            'dgda_enabled' => ['required', 'in:0,1'],
        ]);

        // Master switch wins: tenants cannot opt in while platform is off.
        if (! DgdaService::enabled()) {
            return redirect()
                ->route('settings.index', '#pane-medical')
                ->withErrors(['dgda_enabled' => 'DGDA is disabled at the platform level.']);
        }

        $instituteId = TenantContext::id();
        abort_unless($instituteId, 403);

        $previous = InstituteSetting::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->value('dgda_enabled');

        $newValue = $data['dgda_enabled'] === '1';

        InstituteSetting::updateOrCreate(
            ['institute_id' => $instituteId],
            ['dgda_enabled' => $newValue]
        );

        AuditLog::create([
            'institute_id' => $instituteId,
            'user_type' => $user instanceof InstituteUser ? 'institute_user' : 'system',
            'user_id' => $user->getKey(),
            'action' => 'dgda_enabled_changed',
            'module' => 'settings',
            'record_id' => $instituteId,
            'old_values' => json_encode(['dgda_enabled' => (bool) $previous]),
            'new_values' => json_encode(['dgda_enabled' => $newValue]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        // If just turned ON and custom medicines exist → show migrate prompt
        if (! $previous && $newValue) {
            $customCount = Medicine::where('institute_id', $instituteId)
                ->whereNull('deleted_at')
                ->whereNull('dgda_code')
                ->count();

            if ($customCount > 0) {
                return redirect()
                    ->route('settings.index', '#pane-medical')
                    ->with('migrate_prompt', ['custom_count' => $customCount]);
            }
        }

        return redirect()
            ->route('settings.index', '#pane-medical')
            ->with('status', 'DGDA integration '.($newValue ? 'enabled.' : 'disabled.'));
    }

    /**
     * OPD Queue Display settings (medical.queue_display.*).
     */
    public function updateQueueDisplay(Request $request): RedirectResponse
    {
        $user = $request->user();
        $canManage = $user instanceof InstituteUser
            ? $user->hasPermission('settings.manage')
            : (Workspace::membershipFor($user)?->hasPermission('settings.manage') ?? false);
        abort_unless($canManage, 403);

        $config = config('medicine.queue_display', []);
        $field = 'patient_name_format';
        $definition = $config[$field] ?? [];

        $data = $request->validate([
            $field => ['required', Rule::in(array_keys($definition['options'] ?? []))],
        ], [], [$field => 'Patient name format']);

        $key = $definition['key'] ?? 'medical.queue_display.patient_name_format';
        $previous = (string) Setting::get($key, $definition['default'] ?? '');
        $newValue = $data[$field];

        Setting::set($key, $newValue);

        if ($previous !== $newValue) {
            AuditLog::create([
                'institute_id' => TenantContext::id(),
                'user_type' => $user instanceof InstituteUser ? 'institute_user' : 'system',
                'user_id' => $user->getKey(),
                'action' => 'queue_display_setting_changed',
                'module' => 'settings',
                'record_id' => TenantContext::id(),
                'old_values' => json_encode([$key => $previous]),
                'new_values' => json_encode([$key => $newValue]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        }

        return redirect()
            ->route('settings.index', '#pane-medical')
            ->with('status', 'Queue display settings saved.');
    }

    /**
     * Medical billing journal settings (medical.billing.*).
     *
     * revenue_mode is fiscal-year fenced: switching mid-year would produce a
     * mixed accrual/cash reporting period, so the switch is refused once any
     * medical journal exists in the current open fiscal year.
     */
    public function updateMedicalBilling(Request $request): RedirectResponse
    {
        $user = $request->user();
        $canManage = $user instanceof InstituteUser
            ? $user->hasPermission('settings.manage')
            : (Workspace::membershipFor($user)?->hasPermission('settings.manage') ?? false);
        abort_unless($canManage, 403);

        $instituteId = TenantContext::id();
        abort_unless($instituteId, 403);

        $validated = $request->validate([
            'revenue_mode' => ['required', Rule::in(['accrual', 'cash'])],
            'auto_post' => ['sometimes', 'boolean'],
        ]);

        $journalService = app(MedicalBillingJournalService::class);
        $currentMode = $journalService->getRevenueMode((int) $instituteId);
        $currentAutoPost = $journalService->getAutoPost((int) $instituteId) ? '1' : '0';
        $newAutoPost = $request->boolean('auto_post') ? '1' : '0';

        // Fiscal-year switch guard: only enforced when the mode actually
        // changes; auto_post stays freely toggleable year-round.
        if ($validated['revenue_mode'] !== $currentMode) {
            $fy = FiscalYear::query()
                ->where('institute_id', $instituteId)
                ->where('is_current', true)
                ->first();

            if ($fy !== null && $this->hasMedicalJournalsInFiscalYear((int) $instituteId, $fy)) {
                throw ValidationException::withMessages([
                    'revenue_mode' => 'Revenue mode can only be changed at the start of a fiscal year. Current fiscal year has existing medical journals.',
                ]);
            }
        }

        Setting::set("medical.billing.revenue_mode.{$instituteId}", $validated['revenue_mode']);
        Setting::set("medical.billing.auto_post.{$instituteId}", $newAutoPost);

        if ($validated['revenue_mode'] !== $currentMode || $newAutoPost !== $currentAutoPost) {
            AuditLog::create([
                'institute_id' => $instituteId,
                'user_type' => $user instanceof InstituteUser ? 'institute_user' : 'system',
                'user_id' => $user->getKey(),
                'action' => 'medical_billing_settings_updated',
                'module' => 'settings',
                'record_id' => $instituteId,
                'old_values' => json_encode([
                    'revenue_mode' => $currentMode,
                    'auto_post' => $currentAutoPost,
                ]),
                'new_values' => json_encode([
                    'revenue_mode' => $validated['revenue_mode'],
                    'auto_post' => $newAutoPost,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        }

        return redirect()
            ->route('settings.index', '#pane-medical')
            ->with('status', 'Medical billing settings saved.');
    }

    /**
     * Any sale/receipt/reversal journal the medical billing pipeline wrote
     * inside the given fiscal year (ref_type is what
     * MedicalBillingJournalService stamps on every journal it creates).
     */
    private function hasMedicalJournalsInFiscalYear(int $instituteId, FiscalYear $fy): bool
    {
        return Journal::query()
            ->where('institute_id', $instituteId)
            ->where('ref_type', 'medical_invoice')
            ->whereBetween('journal_date', [$fy->start_date, $fy->end_date])
            ->exists();
    }

    public function dismissMigrate(): RedirectResponse
    {
        return redirect()
            ->route('settings.index', '#pane-medical')
            ->with('status', 'Medicines left unchanged.');
    }

    public function updateCertificateApprovalMode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'certificate_approval_mode' => ['required', 'string', Rule::in(['admin', 'super_admin'])],
        ]);

        $instituteId = TenantContext::id();
        abort_unless($instituteId, 403);

        $previousMode = $this->approvalModeService->getMode($instituteId);
        $newMode = $data['certificate_approval_mode'];

        if ($previousMode === $newMode) {
            return redirect()
                ->route('settings.index', '#pane-academic-setting')
                ->with('status', 'Certificate approval mode is already set to '.($newMode === 'admin' ? 'Admin Controlled' : 'Super Admin Required').'.');
        }

        $this->approvalModeService->setMode($instituteId, $newMode);

        AuditLog::create([
            'institute_id' => $instituteId,
            'user_type' => 'institute_user',
            'user_id' => $request->user()->id ?? auth()->id(),
            'action' => 'certificate_approval_mode_changed',
            'module' => 'settings',
            'record_id' => $instituteId,
            'old_values' => json_encode(['certificate_approval_mode' => $previousMode]),
            'new_values' => json_encode(['certificate_approval_mode' => $newMode]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);

        $modeLabel = $newMode === 'admin' ? 'Admin Controlled' : 'Super Admin Required';

        return redirect()
            ->route('settings.index', '#pane-academic-setting')
            ->with('status', "Certificate approval mode updated to {$modeLabel}.");
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => PasswordPolicy::rules(),
        ]);

        $user = $request->user();

        app(PasswordService::class)->changePassword($user, $data['current_password'], $data['password']);

        return redirect()
            ->route('settings.index', '#pane-account')
            ->with('status', 'Password updated.');
    }

    // Spec-compliant logo upload — delegates to InstituteLogoController logic but keeps route name settings.logo.upload
    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
        ]);

        $instituteId = $this->resolveInstituteId($request->user()) ?? TenantContext::id() ?? Workspace::id();
        abort_unless($instituteId, 403);
        $institute = Institute::findOrFail($instituteId);

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $path = 'institutes/'.$institute->id.'/logo.'.$ext;

        $oldPath = $institute->logo_path_resolved;
        if (! empty($oldPath) && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
        if (! empty($institute->logo) && $institute->logo !== $path && $institute->logo !== $oldPath && Storage::disk('public')->exists($institute->logo)) {
            Storage::disk('public')->delete($institute->logo);
        }
        if (! empty($institute->logo_path) && $institute->logo_path !== $path && $institute->logo_path !== $oldPath && Storage::disk('public')->exists($institute->logo_path)) {
            Storage::disk('public')->delete($institute->logo_path);
        }

        Storage::disk('public')->putFileAs('institutes/'.$institute->id, $file, 'logo.'.$ext);

        $institute->logo_path = $path;
        $institute->save();

        return redirect()->back()->with('success', 'Logo uploaded successfully.');
    }

    public function removeLogo(Request $request): RedirectResponse
    {
        $instituteId = $this->resolveInstituteId($request->user()) ?? TenantContext::id() ?? Workspace::id();
        abort_unless($instituteId, 403);
        $institute = Institute::findOrFail($instituteId);

        $paths = array_filter([$institute->logo, $institute->logo_path, $institute->logo_path_resolved]);
        foreach (array_unique($paths) as $p) {
            if (! empty($p) && Storage::disk('public')->exists($p)) {
                Storage::disk('public')->delete($p);
            }
        }
        $institute->logo_path = null;
        $institute->save();

        return redirect()->back()->with('success', 'Logo removed. Default will be used.');
    }

    private function resolveInstituteId($user): ?int
    {
        if ($user instanceof InstituteUser) {
            return $user->institute_id;
        }

        if ($user instanceof User) {
            return Workspace::membership()?->institution_id;
        }

        return null;
    }

    private function resolveSetting(int $instituteId): InstituteSetting
    {
        $setting = InstituteSetting::query()
            ->where('institute_id', $instituteId)
            ->first();

        if ($setting === null) {
            $defaults = [
                'theme' => 'default',
                'primary_color' => '#0D6EFD',
                'secondary_color' => '#FFC107',
                // 9b-3: timezone via locale config (default 'Asia/Dhaka', unchanged).
                'timezone' => config('locale.date.timezone', 'Asia/Dhaka'),
                'language' => 'bn',
                'date_format' => 'dmy',
            ];

            $industry = Institute::query()->where('id', $instituteId)->value('industry');
            $industryThemeSlug = IndustrySetting::query()
                ->whereIn('industry_key', [$industry, 'all'])
                ->whereNotNull('theme_slug')
                ->orderByRaw('CASE WHEN industry_key = ? THEN 0 ELSE 1 END', [$industry])
                ->value('theme_slug');

            if ($industryThemeSlug !== null) {
                $theme = Theme::query()->where('slug', $industryThemeSlug)->first();
                if ($theme !== null) {
                    $defaults['theme'] = $theme->slug;
                    $defaults['primary_color'] = $theme->primary_color;
                    $defaults['secondary_color'] = $theme->secondary_color;
                }
            }

            $setting = new InstituteSetting($defaults);
        }

        return $setting;
    }
}
