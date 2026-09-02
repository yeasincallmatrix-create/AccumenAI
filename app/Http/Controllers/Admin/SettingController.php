<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Setting;
use App\Models\Theme;
use App\Support\AiConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Auth\PasswordService;
use App\Support\PasswordHash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $pendingStaff = InstituteUser::query()
            ->where('status', 'inactive')
            ->with(['institute', 'role'])
            ->orderByDesc('created_at')
            ->get();

        $activeTheme = null;
        $themeId = $user->preference('theme_id');
        if ($themeId !== null) {
            $activeTheme = Theme::query()->where('status', 'active')->find($themeId);
        }
        if ($activeTheme === null) {
            $activeTheme = Theme::query()->where('is_default', 1)->where('status', 'active')->first();
        }

        return view('admin.settings.index', [
            'admin' => $user,
            'pendingStaff' => $pendingStaff,
            'pendingStaffCount' => $pendingStaff->count(),
            'preferredLanguage' => $user->preferred_language ?? 'en',
            'theme' => $user->preference('theme') ?? 'default',
            'themes' => Theme::query()->where('status', 'active')->orderBy('is_default', 'desc')->orderBy('name')->get(),
            'activeTheme' => $activeTheme,
            'sidebarColor' => $user->preference('sidebar_color'),
            'tallNavigation' => (bool) $user->preference('tall_navigation'),
            'smtpHost' => Setting::get('smtp.host', ''),
            'smtpPort' => Setting::get('smtp.port', '587'),
            'smtpEncryption' => Setting::get('smtp.encryption', 'none'),
            'smtpUsername' => Setting::get('smtp.username', ''),
            'smtpPasswordMasked' => Setting::masked('smtp.password'),
            'smtpConfigured' => Setting::isConfigured('smtp.host'),
            'paymentGateway' => Setting::get('payment.gateway', ''),
            'securityUser' => $user,
            'securityGuard' => $user instanceof PlatformAdmin ? 'platform_admin' : 'institute_user',
            'sessions' => DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->orderByDesc('last_activity')
                ->get(),
            'currentSessionId' => $request->session()->getId(),
            'aiEnabled' => AiConfig::enabled(),
            'provider' => AiConfig::provider(),
            'model' => AiConfig::model(),
            'hasApiKey' => filled(AiConfig::apiKey()),
            'baseUrl' => AiConfig::baseUrl(),
            'globalInstructions' => AiConfig::globalInstructions(),
            'maxTokens' => AiConfig::maxTokens(),
            'temperature' => AiConfig::temperature(),
            'timeout' => AiConfig::timeout(),
            'responseLanguage' => AiConfig::responseLanguage(),
            'dailyLimit' => AiConfig::dailyLimit(),
            'monthlyLimit' => AiConfig::monthlyLimit(),
            'features' => AiConfig::features(),
            'storePrompts' => AiConfig::storePrompts(),
            'availableProviders' => [
                'openai' => 'OpenAI',
                'anthropic' => 'Anthropic (Claude)',
                'gemini' => 'Google Gemini',
                'groq' => 'Groq',
                'custom' => 'Custom (OpenAI-compatible)',
            ],
            'implementedFeatures' => ['assistant' => 'AI Assistant'],
        ]);
    }

    public function account(Request $request): View
    {
        return view('admin.settings.account', [
            'admin' => $request->user(),
            'preferredLanguage' => $request->user()->preferred_language ?? 'en',
        ]);
    }

    public function staff(Request $request): View
    {
        $pendingStaff = InstituteUser::query()
            ->where('status', 'inactive')
            ->with(['institute', 'role'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.settings.staff', [
            'admin' => $request->user(),
            'pendingStaff' => $pendingStaff,
        ]);
    }

    public function password(Request $request): View
    {
        return view('admin.settings.password', [
            'admin' => $request->user(),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => \App\Support\PasswordPolicy::rules(),
        ]);

        $admin = $request->user();

        app(PasswordService::class)->changePassword($admin, $data['current_password'], $data['password']);

        return redirect(route('admin.settings.index').'#pane-account')->with('status', 'Password updated.');
    }

    public function updateLanguage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'language' => ['required', 'in:en,bn'],
        ]);

        $user = $request->user();
        $user->forceFill(['preferred_language' => $data['language']])->save();
        session(['mawa_lang' => $data['language']]);

        return redirect(route('admin.settings.index').'#pane-account')->with('status', 'Language updated.');
    }

    public function staffAction(Request $request, InstituteUser $instituteUser): RedirectResponse
    {
        $action = $request->validate([
            'action' => ['required', 'in:approve,reject'],
        ])['action'];

        if ($action === 'approve') {
            $instituteUser->forceFill(['status' => 'active'])->save();
            $message = $instituteUser->name.' has been approved and can now log in.';
        } else {
            $instituteUser->delete();
            $message = 'Pending registration for '.($instituteUser->name ?? 'staff member').' was rejected.';
        }

        return redirect(route('admin.settings.index').'#pane-staff')->with('status', $message);
    }

    public function appearance(Request $request): View
    {
        $user = $request->user();

        $activeTheme = null;
        $themeId = $user->preference('theme_id');
        if ($themeId !== null) {
            $activeTheme = Theme::query()->where('status', 'active')->find($themeId);
        }
        if ($activeTheme === null) {
            $activeTheme = Theme::query()->where('is_default', 1)->where('status', 'active')->first();
        }

        return view('admin.settings.appearance', [
            'admin' => $user,
            'preferredLanguage' => $user->preferred_language ?? 'en',
            'theme' => $user->preference('theme') ?? 'default',
            'themes' => Theme::query()->where('status', 'active')->orderBy('is_default', 'desc')->orderBy('name')->get(),
            'activeTheme' => $activeTheme,
            'sidebarColor' => $user->preference('sidebar_color'),
            'tallNavigation' => (bool) $user->preference('tall_navigation'),
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme_id' => ['nullable', 'integer'],
            'language' => ['nullable', 'in:en,bn'],
            'sidebar_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tall_navigation' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $activeTheme = null;
        if (filled($data['theme_id'] ?? null)) {
            $activeTheme = Theme::query()->where('status', 'active')->find($data['theme_id']);
        }

        $user->setPreference('theme_id', $activeTheme?->id);
        $user->setPreference('theme', $activeTheme ? ($activeTheme->is_dark ? 'dark' : 'light') : 'default');
        $user->setPreference('sidebar_color', $data['sidebar_color'] ?? null);
        $user->setPreference('tall_navigation', $request->boolean('tall_navigation'));

        if (filled($data['language'] ?? null)) {
            $user->forceFill(['preferred_language' => $data['language']])->save();
            session(['mawa_lang' => $data['language']]);
        } else {
            $user->save();
        }

        return redirect(route('admin.settings.index').'#pane-appearance')->with('status', 'Appearance settings saved.');
    }

    public function mailPayment(Request $request): View
    {
        return view('admin.settings.mail_payment', [
            'admin' => $request->user(),
            'smtpHost' => Setting::get('smtp.host', ''),
            'smtpPort' => Setting::get('smtp.port', '587'),
            'smtpEncryption' => Setting::get('smtp.encryption', 'none'),
            'smtpUsername' => Setting::get('smtp.username', ''),
            'smtpPasswordMasked' => Setting::masked('smtp.password'),
            'paymentGateway' => Setting::get('payment.gateway', ''),
        ]);
    }

    public function updateMailPayment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'string', 'max:10'],
            'smtp_encryption' => ['required', 'in:none,tls,ssl'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'payment_gateway' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::set('smtp.host', $data['smtp_host'] ?? '');
        Setting::set('smtp.port', $data['smtp_port'] ?? '');
        Setting::set('smtp.encryption', $data['smtp_encryption']);
        Setting::set('smtp.username', $data['smtp_username'] ?? '');
        // Blank password must NOT wipe existing — never render plaintext
        if (filled($data['smtp_password'] ?? null) && $data['smtp_password'] !== '••••••••') {
            Setting::set('smtp.password', $data['smtp_password']);
            \App\Models\PlatformAuditLog::record('email', 'smtp.password', 'credential_changed');
        }
        Setting::set('payment.gateway', $data['payment_gateway'] ?? '');
        \App\Models\PlatformAuditLog::record('email', 'smtp.host', 'updated');

        return redirect(route('admin.settings.index').'#pane-mail-payment')->with('status', 'Mail and payment settings saved.');
    }

    public function testMail(Request $request): RedirectResponse
    {
        $host = Setting::get('smtp.host', '');
        $encryption = Setting::get('smtp.encryption', 'none');

        if ($host === '') {
            return back()->withErrors(['smtp_host' => 'Save the SMTP settings first, then send a test email.']);
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) Setting::get('smtp.port', '587'),
            'mail.mailers.smtp.username' => Setting::get('smtp.username', ''),
            'mail.mailers.smtp.password' => Setting::get('smtp.password', ''),
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.from.address' => Setting::get('smtp.from_address', config('mail.from.address')),
            'mail.from.name' => Setting::get('smtp.from_name', config('mail.from.name')),
        ]);

        $to = $request->input('test_email');
        $to = $to !== '' && $to !== null ? $to : $request->user()->email;

        try {
            Mail::raw(
                'This is a test email from the '.config('app.name').' admin panel. Your SMTP settings are working.',
                function ($message) use ($to) {
                    $message->to($to)->subject('Test Email — '.config('app.name'));
                }
            );
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $msg = str_replace((string) Setting::get('smtp.password', ''), '***', $msg);
            return back()->withErrors(['smtp_test' => 'Test email failed: '.substr($msg, 0, 300)]);
        }

        return redirect(route('admin.settings.index').'#pane-mail-payment')->with('status', 'Test email sent to '.$to.'.');
    }
}
