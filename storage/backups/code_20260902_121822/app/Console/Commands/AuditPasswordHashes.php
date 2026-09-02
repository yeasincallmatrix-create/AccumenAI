<?php

namespace App\Console\Commands;

use App\Support\PasswordHash;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit the password hashes across every auth user table and flag any row
 * whose stored hash is no longer a valid bcrypt/argon hash.
 *
 * Motivation: a corrupted hash (e.g. a binlog/shell/import round-trip stripping
 * the leading "$2y$..." prefix) makes BcryptHasher throw
 * "This password does not use the Bcrypt algorithm" and locks the account out.
 *
 * Run periodically (cron / scheduler) so corruption is caught before a user
 * tries to log in:
 *
 *     php artisan auth:audit-hashes
 *
 * Exit code is non-zero when broken hashes are found, so it can be wired into
 * Uptime Kuma / Nagios / CI pipelines.
 */
class AuditPasswordHashes extends Command
{
    protected $signature = 'security:audit-password-hashes
        {--json : Output machine-readable JSON}
        {--fail-fast : Stop scanning after the first bad hash}';

    protected $description = 'Verify every stored password hash is a valid bcrypt/argon hash (read-only, never prints hashes)';

    /**
     * The user tables that store credentials in a "password_hash" column.
     * Keep in sync with config/auth.php providers.
     */
    protected const USER_TABLES = ['platform_admins', 'institute_users', 'users', 'guardians'];

    /**
     * @return int 0 when every hash is valid, 1 when at least one is broken
     */
    public function handle(): int
    {
        $counts = [
            'total' => 0,
            'valid' => 0,
            'empty' => 0,
            'unsupported' => 0,
            'malformed' => 0,
            'suspicious' => 0,
        ];
        $broken = [];
        $scanned = 0;

        foreach (self::USER_TABLES as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $hasUuid = DB::getSchemaBuilder()->hasColumn($table, 'uuid');

            $rows = DB::table($table)
                ->select(array_filter(['id', $hasUuid ? 'uuid' : null, 'password_hash']))
                ->get();

            foreach ($rows as $row) {
                $scanned++;
                $hash = (string) ($row->password_hash ?? '');
                $status = PasswordHash::classify($hash);

                $counts['total']++;

                match ($status) {
                    PasswordHash::STATUS_VALID => $counts['valid']++,
                    PasswordHash::STATUS_EMPTY => $counts['empty']++,
                    PasswordHash::STATUS_UNSUPPORTED => $counts['unsupported']++,
                    PasswordHash::STATUS_MALFORMED => $counts['malformed']++,
                    default => $counts['suspicious']++,
                };

                if ($status === PasswordHash::STATUS_VALID) {
                    continue;
                }

                // Never store or print the hash — only non-sensitive identifier
                $identifier = $row->uuid ?? null;
                if ($identifier === null) {
                    $identifier = $row->id;
                }

                $broken[] = [
                    'table' => $table,
                    'id' => $row->id,
                    'uuid' => $row->uuid ?? null,
                    'status' => $status,
                    // length only, never value
                    'hash_length' => strlen($hash),
                ];

                if ($this->option('fail-fast')) {
                    break 2;
                }
            }
        }

        $counts['suspicious'] = count($broken) > 0 ? count($broken) : 0;
        // suspicious is alias for any non-valid; keep for spec but also compute as above
        // Ensure total = valid + empty + unsupported + malformed

        if ($this->option('json')) {
            $this->line(json_encode([
                'scanned' => $scanned,
                'counts' => $counts,
                'broken' => $broken,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $broken ? 1 : 0;
        }

        $this->info("Scanned {$scanned} password hash(es) across: ".implode(', ', self::USER_TABLES));
        $this->line(sprintf(
            'Counts — total: %d | valid: %d | malformed: %d | unsupported: %d | empty: %d | suspicious: %d',
            $counts['total'],
            $counts['valid'],
            $counts['malformed'],
            $counts['unsupported'],
            $counts['empty'],
            count($broken)
        ));

        if (! $broken) {
            $this->info('All hashes are valid.');

            return 0;
        }

        $this->error(sprintf('Found %d broken password hash(es).', count($broken)));
        foreach ($broken as $b) {
            $idPart = $b['uuid'] ? "uuid {$b['uuid']}" : "id {$b['id']}";
            $this->line(sprintf(
                '  %s #%s — %s (hash_length=%d)',
                $b['table'],
                $idPart,
                $b['status'],
                $b['hash_length']
            ));
        }
        $this->line('');
        $this->warn('A broken hash makes login throw "This password does not use the Bcrypt algorithm".');
        $this->warn('Reset the affected accounts via the normal password-reset flow (canonical PasswordService).');

        return 1;
    }
}
