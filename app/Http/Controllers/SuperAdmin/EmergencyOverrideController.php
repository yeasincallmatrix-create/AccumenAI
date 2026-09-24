<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\ModuleRegistry;
use App\Services\ModuleAccessService;
use App\Services\SuperAdminOverrideService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Phase 5 — Super Admin Emergency Override.
 *
 * The ONLY way to act on a hard-boundary (industry/country) block.
 * Requires: 2FA code, ≥50 char justification, typed confirmation,
 * explicit duration. Writes super_admin_overrides (time-limited),
 * audits to module_access_logs, emails an alert.
 */
class EmergencyOverrideController extends Controller
{
    public function create(Institute $institute): View
    {
        $blocked = $this->blockedModules($institute);

        $activeOverrides = DB::table('super_admin_overrides')
            ->where('institute_id', $institute->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get();

        return view('super-admin.institutes.emergency-override.create', compact(
            'institute',
            'blocked',
            'activeOverrides'
        ));
    }

    public function store(Request $request, Institute $institute): RedirectResponse
    {
        $validated = $request->validate([
            'module_key' => 'required|string|exists:module_registry,key',
            'override_layer' => 'required|string|in:industry,country',
            'reason' => 'required|string|min:50|max:1000',
            'two_factor_code' => 'required|string|size:6|regex:/^[0-9]{6}$/',
            'confirmation_text' => 'required|in:I UNDERSTAND THE RISK',
            'expiry_days' => 'required|integer|in:1,7,30,365',
        ], [
            'reason.min' => 'Justification must be at least 50 characters.',
            'confirmation_text.in' => 'You must type the confirmation phrase exactly: I UNDERSTAND THE RISK',
        ]);

        $admin = $request->user();

        // ── 2FA verification (required) ──────────────────────────────
        if (! $admin || ! $admin->two_factor_secret || ! $admin->two_factor_confirmed_at) {
            return back()->withErrors([
                'two_factor_code' => '❌ 2FA is not enabled on your account — enable it in Admin Security settings first.',
            ])->withInput();
        }

        $secret = $this->decryptSecret((string) $admin->two_factor_secret);
        $valid = app(TwoFactorAuthenticationProvider::class)
            ->verify($secret, (string) $request->input('two_factor_code'));

        if (! $valid) {
            return back()->withErrors([
                'two_factor_code' => '❌ Invalid 2FA code — emergency override denied.',
            ])->withInput();
        }

        // ── The selected layer must ACTUALLY be a hard boundary ─────
        $service = app(ModuleAccessService::class);
        $moduleKey = $validated['module_key'];
        $layer = $validated['override_layer'];

        if ($layer === 'industry' && $service->isIndustryCompatible($institute, $moduleKey)) {
            return back()->withErrors([
                'module_key' => "\"{$moduleKey}\" is industry-compatible — no override needed; use the normal Module Access page.",
            ])->withInput();
        }

        if ($layer === 'country' && $service->isCountryTaxAllowed($moduleKey, $institute)) {
            return back()->withErrors([
                'module_key' => "\"{$moduleKey}\" is allowed for country {$institute->country_code} — no override needed; use the normal Module Access page.",
            ])->withInput();
        }

        // ── Create time-limited emergency override ───────────────────
        $overrideId = app(SuperAdminOverrideService::class)->createOverride(
            $institute,
            $moduleKey,
            $layer,
            $validated['reason'],
            (int) $validated['expiry_days']
        );

        // ── Audit trail (visible on the tenant access-log page) ──────
        $service->logAccess(
            $institute->id,
            $moduleKey,
            'emergency_override',
            $admin->id,
            previousState: null,
            newState: 'enabled',
            packageId: $institute->package_id,
            notes: "[{$layer}] {$validated['reason']}",
            actorType: 'platform_admin',
            riskLevel: 'critical',
        );

        // ── Email alert ──────────────────────────────────────────────
        $to = $admin->email;
        DB::table('super_admin_overrides')->where('id', $overrideId)->update(['email_sent_to' => $to]);

        try {
            Mail::raw(
                "EMERGENCY OVERRIDE APPLIED\n\n"
                . "Tenant: {$institute->name} (#{$institute->id})\n"
                . "Module: {$moduleKey}\n"
                . "Layer: {$layer}\n"
                . "Duration: {$validated['expiry_days']} day(s)\n"
                . "Approved by: {$admin->email}\n"
                . "Expires: " . now()->addDays((int) $validated['expiry_days']) . "\n\n"
                . "Justification:\n{$validated['reason']}\n",
                function ($message) use ($to, $moduleKey) {
                    $message->to($to)
                        ->subject("[AccumenAI] Emergency module override — {$moduleKey}");
                }
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Emergency override email failed', [
                'override_id' => $overrideId,
                'error' => $e->getMessage(),
            ]);
        }

        $service->flushCache($institute->id);

        return redirect()
            ->route('admin.institutes.modules', $institute)
            ->with('success', "Emergency override applied to {$moduleKey} ({$layer}) for {$validated['expiry_days']} day(s). Alert sent to {$to}.");
    }

    /**
     * Modules blocked by a hard boundary for this tenant, grouped by layer.
     *
     * @return array{industry: array<int, string>, country: array<int, string>}
     */
    private function blockedModules(Institute $institute): array
    {
        $service = app(ModuleAccessService::class);

        $industry = [];
        $country = [];

        foreach (ModuleRegistry::where('status', 'active')->orderBy('sort_order')->get() as $module) {
            if (! $service->isIndustryCompatible($institute, $module->key)) {
                $industry[] = $module->key;
            } elseif (! $service->isCountryTaxAllowed($module->key, $institute)) {
                $country[] = $module->key;
            }
        }

        return ['industry' => $industry, 'country' => $country];
    }

    /**
     * two_factor_secret may be stored by Fortify (encryptString) or by
     * legacy code paths (serialize encrypt) — accept both.
     */
    private function decryptSecret(string $encrypted): string
    {
        try {
            return (string) Crypt::decrypt($encrypted);
        } catch (\Throwable $e) {
            return (string) Crypt::decryptString($encrypted);
        }
    }
}
