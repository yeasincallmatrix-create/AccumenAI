<?php
namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\AccountInactivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CleanupInactiveUsers extends Command
{
    protected $signature = 'users:cleanup-inactive {--dry-run : Preview without deleting} {--limit=500 : Max users per run}';
    protected $description = 'Inactivity lifecycle: warnings + soft-delete after 1yr (premium 3yr) — Phase 7: permanent deletion NEVER runs here';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $limit = (int)$this->option('limit');
        $processed=0; $warned=0; $finalWarned=0; $deleted=0;

        User::whereNull('deleted_at')->where('status','active')
            ->select('id','email','last_login_at','created_at','status','deleted_at','inactivity_warning_sent_at','inactivity_final_warning_sent_at')
            ->chunkById(200, function($users) use (&$processed,&$warned,&$finalWarned,&$deleted,$dry,$limit) {
                foreach ($users as $u) {
                    if ($processed >= $limit) return false;
                    $processed++;
                    try {
                        DB::transaction(function() use ($u, &$warned,&$finalWarned,&$deleted,$dry){
                            $locked = User::whereKey($u->id)->lockForUpdate()->first();
                            if (!$locked || $locked->deleted_at || $locked->status !== 'active') return;
                            if (AccountInactivityService::isBootstrapException($locked)) return;
                            // Race: re-evaluate eligibility with fresh data
                            if (AccountInactivityService::isEligibleForDeletion($locked)) {
                                try {
                                    \App\Models\PlatformAuditLog::record('users', 'user.'.$locked->id, 'inactivity_eligible', [
                                        'user_id' => $locked->id,
                                        'retention_days' => AccountInactivityService::retentionDays($locked),
                                    ]);
                                } catch (\Throwable $e) {}
                                // Owner with active institute must not be auto-deleted — requires manual transfer
                                [$allowed,$reason] = AccountDeletionService::canForceDelete($locked);
                                if (!$allowed) {
                                    Log::info('inactivity.skip_active_owner', ['user_id'=>$locked->id,'email'=>self::mask($locked->email),'reason'=>$reason]);
                                    try {
                                        \App\Models\PlatformAuditLog::record('users', 'user.'.$locked->id, 'inactivity_delete_blocked_orphan', [
                                            'user_id' => $locked->id,
                                            'reason' => $reason,
                                        ]);
                                    } catch (\Throwable $e) {}
                                    return;
                                }
                                if ($dry) { $deleted++; return; }
                                Log::info('inactivity.auto_deletion', ['user_id'=>$locked->id,'email'=>self::mask($locked->email),'retention_days'=>AccountInactivityService::retentionDays($locked)]);
                                try {
                                    \App\Models\PlatformAuditLog::record('users', 'user.'.$locked->id, 'inactivity_soft_deleted', [
                                        'user_id' => $locked->id,
                                        'retention_days' => AccountInactivityService::retentionDays($locked),
                                        'effective_login_at' => AccountInactivityService::getEffectiveLastLoginAt($locked)?->toIso8601String(),
                                    ]);
                                } catch (\Throwable $e) {}
                                AccountDeletionService::softDelete($locked);
                                // inactivity_deleted_at stored on soft-deleted row via direct DB (forceFill after soft delete would fail due to SoftDeletes scope)
                                try { DB::table('users')->where('id',$locked->id)->update(['inactivity_deleted_at'=>now()]); } catch (\Throwable $e) {}
                                // Privacy-safe soft deletion notice (idempotent best-effort)
                                try {
                                    $email = $locked->email;
                                    if ($email) {
                                        Mail::raw('Your account has been deactivated due to prolonged inactivity. Contact support within '.config('account_deletion.recovery_days',30).' days if you wish to restore it.', function($m) use ($email){
                                            $m->to($email)->subject('Account deactivated — inactivity');
                                        });
                                    }
                                } catch (\Throwable $e) { report($e); }
                                $deleted++;
                                return;
                            }
                            if (AccountInactivityService::isFinalWarningDue($locked)) {
                                if ($locked->inactivity_final_warning_sent_at && $locked->inactivity_final_warning_sent_at->greaterThan(AccountInactivityService::getEffectiveLastLoginAt($locked) ?? now())) return;
                                if ($dry) { $finalWarned++; return; }
                                self::sendFinalWarning($locked);
                                $locked->forceFill(['inactivity_final_warning_sent_at'=>now()])->save();
                                $finalWarned++;
                                Log::info('inactivity.final_warning', ['user_id'=>$locked->id,'email'=>self::mask($locked->email)]);
                                try { \App\Models\PlatformAuditLog::record('users', 'user.'.$locked->id, 'inactivity_final_warning_sent', ['user_id'=>$locked->id]); } catch (\Throwable $e) {}
                                return;
                            }
                            if (AccountInactivityService::isWarningDue($locked)) {
                                if ($locked->inactivity_warning_sent_at && $locked->inactivity_warning_sent_at->greaterThan(AccountInactivityService::getEffectiveLastLoginAt($locked) ?? now())) return;
                                if ($dry) { $warned++; return; }
                                self::sendWarning($locked);
                                $locked->forceFill(['inactivity_warning_sent_at'=>now()])->save();
                                $warned++;
                                Log::info('inactivity.warning', ['user_id'=>$locked->id,'email'=>self::mask($locked->email)]);
                                try { \App\Models\PlatformAuditLog::record('users', 'user.'.$locked->id, 'inactivity_warning_sent', ['user_id'=>$locked->id]); } catch (\Throwable $e) {}
                            }
                        });
                    } catch (\Throwable $e) {
                        Log::warning('inactivity.row_failed', ['user_id'=>$u->id, 'error'=>substr($e->getMessage(),0,200)]);
                        try { \App\Models\PlatformAuditLog::record('users', 'user.'.$u->id, 'inactivity_concurrency_conflict', ['user_id'=>$u->id]); } catch (\Throwable $ae) {}
                    }
                }
            });

        Log::info('inactivity.cleanup', ['processed'=>$processed,'warned'=>$warned,'final_warned'=>$finalWarned,'deleted'=>$deleted,'dry'=>$dry]);
        $this->info("Processed $processed warned $warned final $finalWarned deleted $deleted");
        return 0;
    }

    protected static function sendWarning(User $user): void
    {
        try {
            $email = $user->email;
            Mail::raw("Your account will be deleted in 30 days due to inactivity. Please login to keep it active.", function($m) use ($email){
                $m->to($email)->subject('Inactivity warning — 30 days');
            });
        } catch (\Throwable $e) { report($e); }
    }
    protected static function sendFinalWarning(User $user): void
    {
        try {
            $email = $user->email;
            Mail::raw("Final warning: your account will be deleted in 7 days due to inactivity. Login now to prevent deletion.", function($m) use ($email){
                $m->to($email)->subject('Final inactivity warning — 7 days');
            });
        } catch (\Throwable $e) { report($e); }
    }
    protected static function mask(string $email): string
    {
        $p=explode('@',$email); if(count($p)!=2) return '***';
        $l=$p[0]; $d=$p[1]; return (strlen($l)<=2?str_repeat('*',strlen($l)):$l[0].str_repeat('*',max(1,strlen($l)-2)).substr($l,-1)).'@'.$d;
    }
}
