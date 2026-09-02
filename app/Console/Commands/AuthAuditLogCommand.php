<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuthAuditLogCommand extends Command
{
    protected $signature = 'auth:audit-log
                            {--email= : Filter by user email}
                            {--days=14 : Look back window in days}
                            {--limit=50 : Max rows}
                            {--json : Output JSON}';

    protected $description = 'Show recent password security events + reset tokens for debugging recurring password issues (read-only)';

    public function handle(): int
    {
        $email = $this->option('email');
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $since = now()->subDays($days);

        $user = null;
        if ($email) {
            $user = DB::table('users')->where('email', $email)->first();
            if (! $user) {
                $this->warn("No users row for email {$email}");
            } else {
                $this->info("User: {$user->email} id={$user->id} status={$user->status} email_verified_at=".($user->email_verified_at ?? 'null')." updated_at={$user->updated_at}");
                // hash preview never prints value — just status
                $hash = $user->password_hash ?? '';
                $status = \App\Support\PasswordHash::classify($hash);
                $this->line("  password_hash status: {$status} (len=".strlen($hash).")");
            }
        }

        // Audit logs for password actions
        $query = DB::table('audit_logs')
            ->where('created_at', '>=', $since)
            ->whereIn('action', ['password_changed','password_change_failed','password_hash_rehashed','password_reset_requested'])
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($user) {
            $query->where('user_id', $user->id)->whereIn('user_type', ['institute_user','system']);
        }

        $logs = $query->get();

        $tokens = collect();
        if ($email) {
            $tokens = DB::table('password_reset_tokens')->where('email', $email)->get();
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'user' => $user,
                'audit_logs' => $logs,
                'password_reset_tokens' => $tokens,
                'since' => $since->toDateTimeString(),
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("Audit logs (last {$days}d, limit {$limit}):");
        if ($logs->isEmpty()) {
            $this->line('  (none)');
        } else {
            foreach ($logs as $log) {
                $this->line("  [{$log->created_at}] action={$log->action} user_id={$log->user_id} user_type={$log->user_type} institute_id=".($log->institute_id ?? 'null')." new_values=".($log->new_values ?? 'null'));
            }
        }

        $this->newLine();
        $this->info('Password reset tokens:');
        if ($tokens->isEmpty()) {
            $this->line('  (none or filtered)');
        } else {
            foreach ($tokens as $t) {
                $this->line("  email={$t->email} created_at={$t->created_at}");
            }
        }

        $this->newLine();
        $this->info('Tip: run security:audit-password-hashes to check for corrupted hashes.');

        return self::SUCCESS;
    }
}
