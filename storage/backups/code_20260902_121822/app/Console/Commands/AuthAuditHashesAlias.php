<?php

namespace App\Console\Commands;

/**
 * Backward-compatible alias for the canonical `security:audit-password-hashes` command.
 * Keeps scheduler / CI that still calls `auth:audit-hashes` functional.
 */
class AuthAuditHashesAlias extends AuditPasswordHashes
{
    protected $signature = 'auth:audit-hashes
        {--json : Output machine-readable JSON}
        {--fail-fast : Stop scanning after the first bad hash}';

    protected $description = 'Alias of security:audit-password-hashes (deprecated — use security:audit-password-hashes)';
}
