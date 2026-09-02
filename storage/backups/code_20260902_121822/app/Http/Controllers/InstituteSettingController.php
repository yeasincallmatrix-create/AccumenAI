<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\IndustrySetting;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Theme;
use App\Models\User;
use App\Services\CertificateApprovalModeService;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Auth\PasswordService;
use App\Support\PasswordHash;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        ]);

        InstituteSetting::updateOrCreate(
            ['institute_id' => $this->resolveInstituteId($request->user())],
            $data
        );

        return redirect()
            ->route('settings.index', '#pane-general')
            ->with('status', 'General settings updated.');
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
                ->with('status', 'Certificate approval mode is already set to ' . ($newMode === 'admin' ? 'Admin Controlled' : 'Super Admin Required') . '.');
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
            'password' => \App\Support\PasswordPolicy::rules(),
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
            'logo' => ['required','image','mimes:jpeg,png,jpg,gif,svg,webp','max:2048'],
        ]);

        $instituteId = $this->resolveInstituteId($request->user()) ?? TenantContext::id() ?? Workspace::id();
        abort_unless($instituteId, 403);
        $institute = Institute::findOrFail($instituteId);

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'png';
        $path = 'institutes/'.$institute->id.'/logo.'.$ext;

        $oldPath = $institute->logo_path_resolved;
        if (!empty($oldPath) && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
        if (!empty($institute->logo) && $institute->logo !== $path && $institute->logo !== $oldPath && Storage::disk('public')->exists($institute->logo)) {
            Storage::disk('public')->delete($institute->logo);
        }
        if (!empty($institute->logo_path) && $institute->logo_path !== $path && $institute->logo_path !== $oldPath && Storage::disk('public')->exists($institute->logo_path)) {
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
            if (!empty($p) && Storage::disk('public')->exists($p)) {
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
                'timezone' => 'Asia/Dhaka',
                'language' => 'bn',
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
