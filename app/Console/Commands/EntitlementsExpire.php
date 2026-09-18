<?php

namespace App\Console\Commands;

use App\Models\InstituteModuleEntitlement;
use App\Services\ModuleAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class EntitlementsExpire extends Command
{
    protected $signature = 'entitlements:expire {--dry-run : Preview transitions without making changes}';

    protected $description = 'Process entitlement expiry and pending activation (active→expired, trialing→expired, pending→active)';

    public function handle(ModuleAccessService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = Carbon::now();
        $affected = 0;
        $flushedInstitutes = [];

        $countPendingToActive = 0;
        $countActiveToExpired = 0;
        $countTrialToExpired = 0;

        // 1. pending → active when starts_at <= now()
        // Future pending must NOT activate early (starts_at > now() stays pending)
        $pending = InstituteModuleEntitlement::withoutGlobalScopes()
            ->where('status', 'pending')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            ->get();

        foreach ($pending as $ent) {
            if (!$dryRun) {
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
                $flushedInstitutes[$ent->institute_id] = true;
            }
            $countPendingToActive++;
            $affected++;
        }

        // Also handle pending with null starts_at? Should activate immediately (no future gate)
        $pendingNoStart = InstituteModuleEntitlement::withoutGlobalScopes()
            ->where('status', 'pending')
            ->whereNull('starts_at')
            ->get();
        foreach ($pendingNoStart as $ent) {
            if (!$dryRun) {
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
                $flushedInstitutes[$ent->institute_id] = true;
            }
            $countPendingToActive++;
            $affected++;
        }

        // 2. active → expired when ends_at < now()
        $activeExpired = InstituteModuleEntitlement::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->get();

        foreach ($activeExpired as $ent) {
            if (!$dryRun) {
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
                $flushedInstitutes[$ent->institute_id] = true;
            }
            $countActiveToExpired++;
            $affected++;
        }

        // 3. trialing → expired when trial_ends_at < now()
        $trialExpired = InstituteModuleEntitlement::withoutGlobalScopes()
            ->where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->get();

        foreach ($trialExpired as $ent) {
            if (!$dryRun) {
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
                $flushedInstitutes[$ent->institute_id] = true;
            }
            $countTrialToExpired++;
            $affected++;
        }

        // Do NOT handle trialing → active; per 63B architecture trialing remains trialing until trial window logic, not auto-promoted.
        // Do NOT reactivate revoked/expired.

        if (!$dryRun) {
            // Flush cache per institute that had effective change
            foreach (array_keys($flushedInstitutes) as $instituteId) {
                $service->flushCache((int) $instituteId);
            }
        }

        // Structured output
        if ($dryRun) {
            $this->newLine();
            $this->info('Entitlement expiry — DRY RUN');
            $this->newLine();
            $this->line("  pending → active:    {$countPendingToActive} rows");
            $this->line("  active  → expired:   {$countActiveToExpired} rows");
            $this->line("  trialing→ expired:   {$countTrialToExpired} rows");
            $this->line("  Total:               {$affected} rows");
            $this->newLine();
            $this->warn('DRY RUN — no changes made.');
        } else {
            $this->newLine();
            $this->info('Entitlement expiry — LIVE');
            $this->newLine();
            $this->line("  pending → active:    {$countPendingToActive} rows");
            $this->line("  active  → expired:   {$countActiveToExpired} rows");
            $this->line("  trialing→ expired:   {$countTrialToExpired} rows");
            $this->line("  Total:               {$affected} rows");
            $this->line("  Institutes flushed:  ".count($flushedInstitutes));
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
