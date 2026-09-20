<?php

namespace App\Console\Commands;

use App\Models\TenantAccessDenial;
use App\Models\TenantAccessGrant;
use App\Services\ModuleAccessService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7b — expire tenant_access_grants / tenant_access_denials whose
 * expires_at has passed.
 *
 * Transitions status 'active' → 'expired' for rows with
 * expires_at < now(). Idempotent (only touches active rows; re-runs
 * are no-ops). Flushes the feature-access cache for every affected
 * institute so computeFeatureAccessMap() picks up the change.
 *
 * No module_access_logs audit rows are written: those logs are keyed
 * on module_key + package_id and a feature/tier grant has no single
 * owning module, so inventing log semantics here would be misleading.
 */
class ProcessExpiredGrants extends Command
{
    protected $signature = 'grants:process-expired {--dry-run : Preview transitions without making changes}';

    protected $description = 'Mark expired tenant access grants and denials (active → expired)';

    public function handle(ModuleAccessService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now = now();

        $expiredGrantIds = TenantAccessGrant::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->pluck('id')
            ->all();

        $expiredDenialIds = TenantAccessDenial::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->pluck('id')
            ->all();

        if ($dryRun) {
            $this->info('DRY RUN: '.count($expiredGrantIds).' grants, '.count($expiredDenialIds).' denials would expire');

            return self::SUCCESS;
        }

        $flushed = [];

        DB::transaction(function () use ($expiredGrantIds, $expiredDenialIds, $service, &$flushed) {
            if (! empty($expiredGrantIds)) {
                $grants = TenantAccessGrant::whereIn('id', $expiredGrantIds)->get();
                foreach ($grants as $grant) {
                    $grant->update(['status' => 'expired']);
                    $flushed[$grant->institute_id] = true;
                }
            }

            if (! empty($expiredDenialIds)) {
                $denials = TenantAccessDenial::whereIn('id', $expiredDenialIds)->get();
                foreach ($denials as $denial) {
                    $denial->update(['status' => 'expired']);
                    $flushed[$denial->institute_id] = true;
                }
            }

            foreach (array_keys($flushed) as $instituteId) {
                $service->flushFeatureCache((int) $instituteId);
            }
        });

        $this->info('Expired: '.count($expiredGrantIds).' grants, '.count($expiredDenialIds).' denials');

        return self::SUCCESS;
    }
}
