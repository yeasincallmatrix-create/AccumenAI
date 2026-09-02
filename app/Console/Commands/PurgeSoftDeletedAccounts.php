<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountDeletionGovernance;
use App\Services\AccountDeletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 7 — Permanent Deletion (separate from inactivity soft-delete).
 *
 * This command ONLY handles FORCE_DELETE of already soft-deleted accounts
 * that have passed the recovery window (default 30 days).
 *
 * It NEVER soft-deletes; soft-delete is exclusively via users:cleanup-inactive.
 * Uses chunkById + lockForUpdate + fresh re-evaluation + per-row isolation.
 */
class PurgeSoftDeletedAccounts extends Command
{
    protected $signature = 'users:purge-soft-deleted {--dry-run : Preview without deleting} {--limit=500 : Max users per run}';
    protected $description = 'Phase 7: permanently delete soft-deleted accounts past the recovery window (default 30d)';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $processed = 0; $purged = 0; $skippedNotEligible = 0; $skippedOrphan = 0;

        User::onlyTrashed()
            ->select('id','email','deleted_at','inactivity_deleted_at','status')
            ->chunkById(200, function ($users) use (&$processed, &$purged, &$skippedNotEligible, &$skippedOrphan, $dry, $limit) {
                foreach ($users as $u) {
                    if ($processed >= $limit) return false;
                    $processed++;
                    try {
                        DB::transaction(function () use ($u, &$purged, &$skippedNotEligible, &$skippedOrphan, $dry) {
                            $locked = User::withTrashed()->whereKey($u->id)->lockForUpdate()->first();
                            if (!$locked || $locked->deleted_at === null) return;

                            // Fresh governance re-evaluation (DB timestamps authoritative)
                            [$allowed, $reason, $code] = AccountDeletionGovernance::canForceDelete($locked);
                            if (! $allowed) {
                                if ($code === 'orphan_risk') {
                                    $skippedOrphan++;
                                    Log::info('purge.skip_orphan', ['user_id' => $locked->id, 'email' => self::mask($locked->email), 'reason' => $reason]);
                                    try { \App\Models\PlatformAuditLog::record('users', 'user.'.$locked->id, 'purge_blocked_orphan', ['user_id'=>$locked->id,'reason'=>$reason]); } catch (\Throwable $e) {}
                                } else {
                                    $skippedNotEligible++;
                                    Log::info('purge.skip_not_eligible', ['user_id' => $locked->id, 'reason' => $reason, 'code' => $code]);
                                }
                                return;
                            }

                            // Permanent deletion warning notice (idempotent best-effort) before hard delete
                            if (! $dry) {
                                try {
                                    if ($locked->email) {
                                        \Illuminate\Support\Facades\Mail::raw(
                                            'Final notice: your account is being permanently deleted. This action is irreversible.',
                                            function ($m) use ($locked) { $m->to($locked->email)->subject('Account permanently deleted'); }
                                        );
                                    }
                                } catch (\Throwable $e) { report($e); }
                            }

                            if ($dry) { $purged++; return; }

                            Log::info('purge.force_delete', ['user_id' => $locked->id, 'email' => self::mask($locked->email)]);
                            try { \App\Models\PlatformAuditLog::record('users', 'user.'.$locked->id, 'purge_force_delete', ['user_id'=>$locked->id]); } catch (\Throwable $e) {}

                            AccountDeletionService::forceDelete($locked);
                            $purged++;
                        });
                    } catch (\Throwable $e) {
                        Log::warning('purge.row_failed', ['user_id' => $u->id, 'error' => substr($e->getMessage(), 0, 200)]);
                        try { \App\Models\PlatformAuditLog::record('users', 'user.'.$u->id, 'purge_concurrency_conflict', ['user_id'=>$u->id]); } catch (\Throwable $ae) {}
                    }
                }
            });

        Log::info('purge.cleanup', ['processed' => $processed, 'purged' => $purged, 'skipped_not_eligible' => $skippedNotEligible, 'skipped_orphan' => $skippedOrphan, 'dry' => $dry]);
        $this->info("Processed $processed purged $purged skipped_not_eligible $skippedNotEligible skipped_orphan $skippedOrphan");

        return 0;
    }

    protected static function mask(string $email): string
    {
        $p = explode('@', $email); if (count($p) != 2) return '***';
        $l = $p[0]; $d = $p[1]; return (strlen($l) <= 2 ? str_repeat('*', strlen($l)) : $l[0].str_repeat('*', max(1, strlen($l)-2)).substr($l, -1)).'@'.$d;
    }
}
