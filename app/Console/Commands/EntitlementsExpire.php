<?php

namespace App\Console\Commands;

use App\Models\InstituteModuleEntitlement;
use App\Services\ModuleAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EntitlementsExpire extends Command
{
    protected $signature = 'entitlements:expire';

    protected $description = 'Process entitlement expiry and pending activation (active→expired, trialing→expired, pending→active)';

    public function handle(ModuleAccessService $service): int
    {
        $now = Carbon::now();
        $affected = 0;
        $flushedInstitutes = [];

        // 1. pending → active when starts_at <= now()
        // Future pending must NOT activate early (starts_at > now() stays pending)
        $pending = InstituteModuleEntitlement::withoutGlobalScopes()
            ->where('status', 'pending')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            ->get();

        foreach ($pending as $ent) {
            $packageId = \App\Models\Institute::withoutGlobalScopes()->where('id', $ent->institute_id)->value('package_id');
            $service->logAccess(
                $ent->institute_id,
                $ent->module_key,
                'entitlement_granted',
                null,
                'pending',
                'active',
                $packageId,
                'Activated via entitlements:expire'
            );
            $ent->update(['status' => 'active']);
            $affected++;
            $flushedInstitutes[$ent->institute_id] = true;
        }

        // Also handle pending with null starts_at? Should activate immediately (no future gate)
        $pendingNoStart = InstituteModuleEntitlement::withoutGlobalScopes()
            ->where('status', 'pending')
            ->whereNull('starts_at')
            ->get();
        foreach ($pendingNoStart as $ent) {
            $packageId = \App\Models\Institute::withoutGlobalScopes()->where('id', $ent->institute_id)->value('package_id');
            $service->logAccess(
                $ent->institute_id,
                $ent->module_key,
                'entitlement_granted',
                null,
                'pending',
                'active',
                $packageId,
                'Activated via entitlements:expire (no starts_at)'
            );
            $ent->update(['status' => 'active']);
            $affected++;
            $flushedInstitutes[$ent->institute_id] = true;
        }

        // 2. active → expired when ends_at < now()
        $activeExpired = InstituteModuleEntitlement::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->get();

        foreach ($activeExpired as $ent) {
            $packageId = \App\Models\Institute::withoutGlobalScopes()->where('id', $ent->institute_id)->value('package_id');
            $service->logAccess(
                $ent->institute_id,
                $ent->module_key,
                'entitlement_expired',
                null,
                'active',
                'expired',
                $packageId,
                'Expired via entitlements:expire'
            );
            $ent->update(['status' => 'expired']);
            $affected++;
            $flushedInstitutes[$ent->institute_id] = true;
        }

        // 3. trialing → expired when trial_ends_at < now()
        $trialExpired = InstituteModuleEntitlement::withoutGlobalScopes()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->get();

        foreach ($trialExpired as $ent) {
            $packageId = \App\Models\Institute::withoutGlobalScopes()->where('id', $ent->institute_id)->value('package_id');
            $service->logAccess(
                $ent->institute_id,
                $ent->module_key,
                'trial_expired',
                null,
                'trialing',
                'expired',
                $packageId,
                'Trial expired via entitlements:expire'
            );
            // Also log entitlement_expired for consistency if needed
            $service->logAccess(
                $ent->institute_id,
                $ent->module_key,
                'entitlement_expired',
                null,
                'trialing',
                'expired',
                $packageId,
                'Entitlement expired (trial) via entitlements:expire'
            );
            $ent->update(['status' => 'expired']);
            $affected++;
            $flushedInstitutes[$ent->institute_id] = true;
        }

        // Do NOT handle trialing → active; per 63B architecture trialing remains trialing until trial window logic, not auto-promoted.
        // Do NOT reactivate revoked/expired.

        // Flush cache per institute that had effective change
        foreach (array_keys($flushedInstitutes) as $instituteId) {
            $service->flushCache((int) $instituteId);
        }

        $this->info("Processed {$affected} entitlement(s); flushed ".count($flushedInstitutes)." institute(s).");

        return self::SUCCESS;
    }
}
