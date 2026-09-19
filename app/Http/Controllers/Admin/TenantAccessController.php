<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\ModuleAccessLog;
use App\Models\TenantAccessDenial;
use App\Models\TenantAccessGrant;
use App\Services\ModuleAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TenantAccessController extends Controller
{
    public function show(Institute $institute): View
    {
        $grants = TenantAccessGrant::where('institute_id', $institute->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $denials = TenantAccessDenial::where('institute_id', $institute->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $features = FeatureRegistry::orderBy('module_key')
            ->orderBy('sort_order')
            ->get();

        $effectiveMap = $this->effectiveMap($institute, $grants, $denials);

        return view('admin.tenants.access', compact(
            'institute', 'grants', 'denials', 'features', 'effectiveMap'
        ));
    }

    public function addGrant(Request $request, Institute $institute): RedirectResponse
    {
        $validated = $request->validate([
            'grant_type' => ['required', 'in:module,feature,tier'],
            'grant_key' => ['required', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $adminId = $request->user()?->id;

        DB::transaction(function () use ($institute, $validated, $adminId) {
            TenantAccessGrant::create([
                'institute_id' => $institute->id,
                'grant_type' => $validated['grant_type'],
                'grant_key' => $validated['grant_key'],
                'granted_by' => $adminId,
                'granted_at' => now(),
                'expires_at' => $validated['expires_at'] ?? null,
                'reason' => $validated['reason'] ?? null,
                'status' => 'active',
            ]);
        });

        app(ModuleAccessService::class)->flushFeatureCache($institute->id);

        $this->audit($request, $institute->id, $institute->package_id,
            'access_grant_added', null,
            "{$validated['grant_type']}:{$validated['grant_key']}",
            "Grant '{$validated['grant_key']}' added for '{$institute->name}'");

        return back()->with('success', 'Access grant added successfully.');
    }

    public function revokeGrant(
        Request $request,
        Institute $institute,
        TenantAccessGrant $grant
    ): RedirectResponse {
        if ((int) $grant->institute_id !== (int) $institute->id) {
            abort(404, 'Grant does not belong to this institute.');
        }

        if ($grant->status !== 'revoked') {
            DB::transaction(function () use ($grant, $request) {
                $grant->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoked_by' => $request->user()?->id,
                ]);
            });

            app(ModuleAccessService::class)->flushFeatureCache($institute->id);

            $this->audit($request, $institute->id, $institute->package_id,
                'access_grant_revoked', 'active', 'revoked',
                "Grant '{$grant->grant_key}' revoked for '{$institute->name}'");
        }

        return back()->with('success', 'Access grant revoked.');
    }

    public function addDenial(Request $request, Institute $institute): RedirectResponse
    {
        $validated = $request->validate([
            'deny_type' => ['required', 'in:module,feature'],
            'deny_key' => ['required', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $adminId = $request->user()?->id;

        DB::transaction(function () use ($institute, $validated, $adminId) {
            TenantAccessDenial::create([
                'institute_id' => $institute->id,
                'deny_type' => $validated['deny_type'],
                'deny_key' => $validated['deny_key'],
                'denied_by' => $adminId,
                'denied_at' => now(),
                'expires_at' => $validated['expires_at'] ?? null,
                'reason' => $validated['reason'],
                'status' => 'active',
            ]);
        });

        app(ModuleAccessService::class)->flushFeatureCache($institute->id);

        $this->audit($request, $institute->id, $institute->package_id,
            'access_denial_added', null,
            "{$validated['deny_type']}:{$validated['deny_key']}",
            "Denial '{$validated['deny_key']}' added for '{$institute->name}'");

        return back()->with('success', 'Access denial added successfully.');
    }

    public function liftDenial(
        Request $request,
        Institute $institute,
        TenantAccessDenial $denial
    ): RedirectResponse {
        if ((int) $denial->institute_id !== (int) $institute->id) {
            abort(404, 'Denial does not belong to this institute.');
        }

        if ($denial->status !== 'lifted') {
            DB::transaction(function () use ($denial, $request) {
                $denial->update([
                    'status' => 'lifted',
                    'lifted_at' => now(),
                    'lifted_by' => $request->user()?->id,
                ]);
            });

            app(ModuleAccessService::class)->flushFeatureCache($institute->id);

            $this->audit($request, $institute->id, $institute->package_id,
                'access_denial_lifted', 'active', 'lifted',
                "Denial '{$denial->deny_key}' lifted for '{$institute->name}'");
        }

        return back()->with('success', 'Access denial lifted.');
    }

    /**
     * Build the display effective map for an institute.
     *
     * Base comes from ModuleAccessService::getFeatureAccessMap(), then
     * active (non-expired) denials force false and active (non-expired)
     * grants force true for display. Each entry:
     *   ['state' => bool, 'source' => string, 'reason' => ?string]
     *
     * NOTE: tenant_access_grants / tenant_access_denials are not yet
     * wired into the ModuleAccessService gate itself — this overlay is
     * display-only until the service consumes these tables.
     */
    private function effectiveMap(
        Institute $institute,
        $grants,
        $denials
    ): array {
        $base = app(ModuleAccessService::class)->getFeatureAccessMap($institute);

        $now = now();

        $activeDenials = $denials->filter(fn ($d) =>
            $d->status === 'active'
            && ($d->expires_at === null || $d->expires_at->gt($now))
        );

        $activeGrants = $grants->filter(fn ($g) =>
            $g->status === 'active'
            && ($g->expires_at === null || $g->expires_at->gt($now))
        );

        $map = [];
        foreach ($base as $key => $enabled) {
            $modulePrefix = explode('.', $key, 2)[0];

            $denial = $activeDenials->first(fn ($d) =>
                ($d->deny_type === 'feature' && $d->deny_key === $key)
                || ($d->deny_type === 'module' && $d->deny_key === $modulePrefix)
            );

            if ($denial) {
                $map[$key] = [
                    'state' => false,
                    'source' => 'denial',
                    'reason' => $denial->reason,
                ];
                continue;
            }

            $grant = $activeGrants->first(fn ($g) =>
                ($g->grant_type === 'feature' && $g->grant_key === $key)
                || ($g->grant_type === 'module' && $g->grant_key === $modulePrefix)
                || ($g->grant_type === 'tier')
            );

            if ($grant) {
                $map[$key] = [
                    'state' => true,
                    'source' => 'grant:' . $grant->grant_type,
                    'reason' => $grant->reason,
                ];
                continue;
            }

            $map[$key] = [
                'state' => (bool) $enabled,
                'source' => 'package',
                'reason' => null,
            ];
        }

        return $map;
    }

    private function audit(
        Request $request,
        ?int $instituteId,
        ?int $packageId,
        string $action,
        ?string $previous,
        ?string $next,
        string $notes
    ): void {
        $moduleKey = 'tenant_access';
        if (str_contains($notes, '.')) {
            $candidate = explode(' ', $notes)[0] ?? null;
            if (is_string($candidate) && str_contains($candidate, '.')) {
                $moduleKey = explode('.', trim($candidate, "'"), 2)[0];
            }
        }

        ModuleAccessLog::create([
            'institute_id' => $instituteId,
            'module_key' => $moduleKey,
            'action' => $action,
            'actor_id' => $request->user()?->id,
            'actor_type' => 'platform_admin',
            'previous_state' => $previous,
            'new_state' => $next,
            'package_id' => $packageId,
            'notes' => $notes,
        ]);
    }
}
