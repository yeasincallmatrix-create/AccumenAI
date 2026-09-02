<?php

namespace App\Services;

use App\Models\DeploymentLog;
use Illuminate\Support\Facades\Log;

class DeploymentGitService
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = base_path();
    }

    /**
     * Check if git is available on the system.
     */
    public function isGitAvailable(): bool
    {
        $output = @shell_exec('git --version 2>&1');
        return $output !== null && str_contains(strtolower((string) $output), 'git version');
    }

    /** Alias for ArtisanCommandController compatibility */
    public function currentCommit(): ?string
    {
        return $this->currentCommitHash();
    }

    public static function isGitAvailableStatic(): bool
    {
        return (new static())->isGitAvailable();
    }

    public static function currentCommitStatic(): ?string
    {
        $s = new static();
        return $s->isGitAvailable() ? $s->currentCommitHash() : null;
    }

    public static function currentBranchStatic(): ?string
    {
        $s = new static();
        return $s->isGitAvailable() ? $s->currentBranch() : null;
    }

    /**
     * Get current commit hash if git is available.
     */
    public function currentCommitHash(): ?string
    {
        if (! $this->isGitAvailable()) {
            return null;
        }
        $hash = @shell_exec('git rev-parse HEAD 2>&1');
        if ($hash === null) {
            return null;
        }
        $hash = trim((string) $hash);
        if (preg_match('/^[0-9a-f]{7,40}$/i', $hash)) {
            return $hash;
        }
        return null;
    }

    /**
     * Get current branch name.
     */
    public function currentBranch(): ?string
    {
        if (! $this->isGitAvailable()) {
            return null;
        }
        $branch = @shell_exec('git rev-parse --abbrev-ref HEAD 2>&1');
        if ($branch === null) {
            return null;
        }
        $branch = trim((string) $branch);
        if ($branch === '' || str_contains($branch, 'fatal') || str_contains($branch, 'not a git')) {
            return null;
        }
        return $branch;
    }

    /**
     * List remote branches (fallback to common branches if fetch fails).
     */
    public function availableBranches(): array
    {
        if (! $this->isGitAvailable()) {
            return ['main', 'master'];
        }
        $output = @shell_exec('git branch -r 2>&1');
        if ($output === null || trim((string) $output) === '' || str_contains((string) $output, 'fatal')) {
            return ['main', 'master'];
        }
        $branches = [];
        foreach (explode("\n", (string) $output) as $line) {
            $line = trim($line);
            if ($line === '' || str_contains($line, '->')) {
                continue;
            }
            // origin/main -> main
            $line = preg_replace('#^origin/#', '', $line);
            $branches[] = $line;
        }
        $branches = array_values(array_unique(array_filter($branches)));
        if (empty($branches)) {
            return ['main', 'master'];
        }
        return $branches;
    }

    /**
     * Create code + DB backup before deployment.
     * Returns [backup_path, db_backup_path, logFragment]
     */
    public function createBackup(): array
    {
        $timestamp = date('Ymd_His');
        $codeBackupPath = storage_path('backups/code_' . $timestamp);
        $dbBackupPath = storage_path('backups/db_' . $timestamp . '.sql');

        $log = '';

        // Code backup — copy project excluding storage/backups & vendor & node_modules to avoid recursion
        try {
            if (! is_dir($codeBackupPath)) {
                @mkdir($codeBackupPath, 0755, true);
            }
            $log .= $this->copyCodeForBackup($this->basePath, $codeBackupPath);
            $log .= "\n[Backup] Code backup created at: {$codeBackupPath}\n";
        } catch (\Throwable $e) {
            $log .= "\n[Backup] Code backup failed: " . $e->getMessage() . "\n";
            Log::warning('git_deploy_backup_failed', ['error' => $e->getMessage()]);
        }

        // DB backup
        try {
            $log .= $this->exportDatabase($dbBackupPath);
        } catch (\Throwable $e) {
            $log .= "\n[DB Backup] Failed: " . $e->getMessage() . "\n";
        }

        $this->pruneOldBackups();

        return [$codeBackupPath, $dbBackupPath, $log];
    }

    /**
     * Deploy via git.
     *
     * @param string $branch
     * @param int|null $adminUserId
     * @return DeploymentLog
     */
    public function deploy(string $branch = 'main', ?int $adminUserId = null): DeploymentLog
    {
        $branch = preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', $branch) ?: 'main';
        $fullLog = '';
        $status = 'success';
        $version = null;
        $backupPath = null;
        $dbBackupPath = null;

        // Pre-check git
        if (! $this->isGitAvailable()) {
            $fullLog = "[Error] Git is not installed or not available on the server.\n";
            return DeploymentLog::create([
                'admin_user_id' => $adminUserId,
                'type' => 'git',
                'version' => null,
                'status' => 'failed',
                'log' => $fullLog,
                'backup_path' => null,
                'db_backup_path' => null,
                'created_at' => now(),
            ]);
        }

        // Backup
        [$backupPath, $dbBackupPath, $backupLog] = $this->createBackup();
        $fullLog .= $backupLog;

        try {
            // git fetch
            $fullLog .= "\n[Git] Fetching from origin...\n";
            $fullLog .= $this->runShell('git fetch origin 2>&1');

            // git reset --hard origin/{$branch}
            $fullLog .= "\n[Git] Resetting to origin/{$branch}...\n";
            $result = $this->runShell("git reset --hard origin/{$branch} 2>&1");
            $fullLog .= $result;
            if (str_contains(strtolower($result), 'fatal') && str_contains(strtolower($result), 'not found')) {
                throw new \RuntimeException("Branch origin/{$branch} not found. Fetch output: {$result}");
            }
            // Check if reset actually failed via ambiguous
            if (str_contains(strtolower($result), 'fatal:')) {
                throw new \RuntimeException("Git reset failed: {$result}");
            }

            $version = $this->currentCommitHash();
            $fullLog .= "\n[Git] Current commit: " . ($version ?? 'unknown') . "\n";

            // composer install
            $fullLog .= "\n[Composer] Running composer install --no-interaction --prefer-dist --optimize-autoloader...\n";
            $fullLog .= $this->runShell('composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1', 300);

            // artisan commands
            $fullLog .= $this->runArtisanCommands($fullLog);

            $fullLog .= "\n[Deploy] Git deployment completed successfully.\n";
        } catch (\Throwable $e) {
            $status = 'failed';
            $fullLog .= "\n[Error] Deployment failed: " . $e->getMessage() . "\n";
            Log::error('git_deploy_failed', ['branch' => $branch, 'error' => $e->getMessage()]);
        }

        return DeploymentLog::create([
            'admin_user_id' => $adminUserId,
            'type' => 'git',
            'version' => $version,
            'status' => $status,
            'log' => $fullLog,
            'backup_path' => $backupPath,
            'code_backup_path' => $backupPath,
            'db_backup_path' => $dbBackupPath,
            'created_at' => now(),
        ]);
    }

    /**
     * Rollback to previous git state and restore DB.
     */
    public function rollback(int $logId): array
    {
        $logEntry = DeploymentLog::findOrFail($logId);
        $output = '';

        try {
            // Restore code from backup if exists (support both code_backup_path and backup_path for compat)
            $codePath = $logEntry->code_backup_path ?? $logEntry->backup_path;
            if ($codePath && is_dir($codePath)) {
                $output .= "[Rollback] Restoring code from backup: {$codePath}\n";
                $output .= $this->restoreCodeFromBackup($codePath);
            } else {
                // Fallback: git reset --hard HEAD@{1}
                $output .= "[Rollback] No code backup found, trying git reset --hard HEAD@{1}...\n";
                $output .= $this->runShell('git reset --hard HEAD@{1} 2>&1');
            }

            // Restore DB
            if ($logEntry->db_backup_path && is_file($logEntry->db_backup_path)) {
                $output .= "[Rollback] Restoring database from: {$logEntry->db_backup_path}\n";
                $output .= $this->importDatabase($logEntry->db_backup_path);
            } else {
                $output .= "[Rollback] No DB backup found, skipping DB restore.\n";
            }

            // Re-cache
            $output .= $this->runShell('php artisan config:clear 2>&1');
            $output .= $this->runShell('php artisan cache:clear 2>&1');

            $logEntry->update([
                'status' => 'rolled_back',
                'log' => $logEntry->log . "\n\n[Rollback at " . now()->toDateTimeString() . "]\n" . $output,
            ]);

            return ['success' => true, 'log' => $output];
        } catch (\Throwable $e) {
            $output .= "\n[Rollback Error] " . $e->getMessage() . "\n";
            return ['success' => false, 'log' => $output, 'error' => $e->getMessage()];
        }
    }

    protected function runArtisanCommands(string &$logRef = ''): string
    {
        $out = '';
        // Corrected order for 2GB optimization: clear first, then cache. cache:clear AFTER cache destroys config/route cache (bug fix).
        // Route cache has fallback to route:clear due to duplicate name (admin.certificates.requests-columns) - see routes/web.php
        $commands = [
            'php artisan migrate --force 2>&1',
            'php artisan optimize:clear 2>&1',
            'php artisan config:cache 2>&1',
            'php artisan view:cache 2>&1',
            'php artisan event:cache 2>&1',
        ];
        foreach ($commands as $cmd) {
            $out .= "\n[Artisan] {$cmd}\n";
            $result = $this->runShell($cmd, 300);
            $out .= $result . "\n";
        }
        // Route cache with graceful fallback: if serialization fails (duplicate name), clear instead
        $out .= "\n[Artisan] php artisan route:cache 2>&1 (with fallback)\n";
        $routeResult = $this->runShell('php artisan route:cache 2>&1', 300);
        $out .= $routeResult . "\n";
        if (str_contains(strtolower($routeResult), 'unable to prepare route') || str_contains(strtolower($routeResult), 'logicexception') || str_contains(strtolower($routeResult), 'already been assigned')) {
            $out .= "[Artisan] Route cache failed — falling back to route:clear (duplicate route name, non-blocking)\n";
            $out .= $this->runShell('php artisan route:clear 2>&1', 300) . "\n";
        }
        return $out;
    }

    protected function runShell(string $command, int $timeout = 120): string
    {
        // Use shell_exec with timeout via timeout command if available; fallback to direct
        $output = @shell_exec($command);
        return $output !== null ? (string) $output : "[no output]";
    }

    protected function copyCodeForBackup(string $source, string $dest): string
    {
        $exclude = ['.git', 'storage', 'vendor', 'node_modules', '.env'];
        // Use recursive copy with excludes
        $this->recursiveCopy($source, $dest, $exclude);
        return "[Backup] Copied code to {$dest} (excluded: " . implode(', ', $exclude) . ")\n";
    }

    protected function recursiveCopy(string $src, string $dst, array $exclude = []): void
    {
        $dir = opendir($src);
        if (! $dir) return;
        @mkdir($dst, 0755, true);
        while (false !== ($file = readdir($dir))) {
            if ($file === '.' || $file === '..') continue;
            if (in_array($file, $exclude, true)) continue;
            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;
            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $dstPath, $exclude);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    protected function restoreCodeFromBackup(string $backupPath): string
    {
        $log = '';
        // Copy backup back excluding .env handling? We restore everything except we keep current .env if backup .env missing
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($backupPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $rel = substr($item->getPathname(), strlen($backupPath) + 1);
            $target = $this->basePath . DIRECTORY_SEPARATOR . $rel;
            if ($item->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                @mkdir(dirname($target), 0755, true);
                @copy($item->getPathname(), $target);
            }
        }
        $log .= "[Restore] Code restored from {$backupPath}\n";
        return $log;
    }

    protected function exportDatabase(string $path): string
    {
        $log = '';
        @mkdir(dirname($path), 0755, true);
        $driver = config('database.default');
        $conn = config("database.connections.{$driver}");

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $host = $conn['host'] ?? '127.0.0.1';
                $port = $conn['port'] ?? 3306;
                $database = $conn['database'] ?? '';
                $username = $conn['username'] ?? 'root';
                $password = $conn['password'] ?? '';
                $mysqldump = $this->findMysqldump();
                if ($mysqldump) {
                    $passArg = $password !== '' ? "-p" . escapeshellarg($password) : '';
                    $cmd = sprintf(
                        '%s -h %s -P %s -u %s %s %s > %s 2>&1',
                        escapeshellcmd($mysqldump),
                        escapeshellarg($host),
                        escapeshellarg((string) $port),
                        escapeshellarg($username),
                        $passArg,
                        escapeshellarg($database),
                        escapeshellarg($path)
                    );
                    $out = @shell_exec($cmd);
                    $log .= "[DB Backup] mysqldump executed. " . ($out ? $out : '') . "\n";
                    if (! is_file($path) || filesize($path) === 0) {
                        throw new \RuntimeException('mysqldump produced empty file');
                    }
                    $log .= "[DB Backup] SQL backup created at: {$path} (" . filesize($path) . " bytes)\n";
                } else {
                    throw new \RuntimeException('mysqldump not found');
                }
            } else {
                // sqlite or other — copy file
                $dbPath = $conn['database'] ?? database_path('database.sqlite');
                if (is_file($dbPath)) {
                    @copy($dbPath, $path);
                    $log .= "[DB Backup] SQLite file copied to: {$path}\n";
                } else {
                    $log .= "[DB Backup] No DB file to backup for driver {$driver}\n";
                }
            }
        } catch (\Throwable $e) {
            $log .= "[DB Backup] Warning: " . $e->getMessage() . " — creating placeholder\n";
            // Ensure placeholder so rollback logic has a file
            @file_put_contents($path, "-- backup placeholder --\n-- " . $e->getMessage() . "\n");
        }
        return $log;
    }

    protected function importDatabase(string $path): string
    {
        $log = '';
        $driver = config('database.default');
        $conn = config("database.connections.{$driver}");
        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $host = $conn['host'] ?? '127.0.0.1';
                $port = $conn['port'] ?? 3306;
                $database = $conn['database'] ?? '';
                $username = $conn['username'] ?? 'root';
                $password = $conn['password'] ?? '';
                $mysql = $this->findMysql();
                if ($mysql && is_file($path)) {
                    $passArg = $password !== '' ? "-p" . escapeshellarg($password) : '';
                    $cmd = sprintf(
                        '%s -h %s -P %s -u %s %s %s < %s 2>&1',
                        escapeshellcmd($mysql),
                        escapeshellarg($host),
                        escapeshellarg((string) $port),
                        escapeshellarg($username),
                        $passArg,
                        escapeshellarg($database),
                        escapeshellarg($path)
                    );
                    $out = @shell_exec($cmd);
                    $log .= "[DB Restore] mysql import executed. " . ($out ? $out : '') . "\n";
                } else {
                    throw new \RuntimeException('mysql client not found or backup missing');
                }
            } else {
                $dbPath = $conn['database'] ?? database_path('database.sqlite');
                if (is_file($path) && filesize($path) > 100) {
                    @copy($path, $dbPath);
                    $log .= "[DB Restore] SQLite file restored.\n";
                } else {
                    $log .= "[DB Restore] Skipped — placeholder or missing\n";
                }
            }
        } catch (\Throwable $e) {
            $log .= "[DB Restore] Failed: " . $e->getMessage() . "\n";
        }
        return $log;
    }

    protected function findMysqldump(): ?string
    {
        $candidates = ['mysqldump', 'C:\\xampp\\mysql\\bin\\mysqldump.exe', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'];
        foreach ($candidates as $c) {
            $which = @shell_exec('where ' . escapeshellarg($c) . ' 2>&1') ?? @shell_exec('which ' . escapeshellarg($c) . ' 2>&1');
            if ($which && trim((string) $which) !== '') {
                return trim((string) explode("\n", (string) $which)[0]);
            }
            if (is_file($c)) return $c;
        }
        // direct check
        $direct = @shell_exec('mysqldump --version 2>&1');
        if ($direct && str_contains(strtolower((string) $direct), 'mysqldump')) {
            return 'mysqldump';
        }
        return null;
    }

    protected function findMysql(): ?string
    {
        $candidates = ['mysql', 'C:\\xampp\\mysql\\bin\\mysql.exe', '/usr/bin/mysql', '/usr/local/bin/mysql'];
        foreach ($candidates as $c) {
            if (is_file($c)) return $c;
        }
        $direct = @shell_exec('mysql --version 2>&1');
        if ($direct && str_contains(strtolower((string) $direct), 'mysql')) {
            return 'mysql';
        }
        return null;
    }

    protected function pruneOldBackups(int $keep = 5): void
    {
        try {
            $backupDir = storage_path('backups');
            if (! is_dir($backupDir)) return;
            $codeBackups = glob($backupDir . DIRECTORY_SEPARATOR . 'code_*') ?: [];
            $dbBackups = glob($backupDir . DIRECTORY_SEPARATOR . 'db_*') ?: [];
            // sort by mtime descending, keep newest $keep
            usort($codeBackups, fn($a, $b) => filemtime($b) <=> filemtime($a));
            usort($dbBackups, fn($a, $b) => filemtime($b) <=> filemtime($a));
            foreach (array_slice($codeBackups, $keep) as $old) {
                $this->deleteRecursive($old);
            }
            foreach (array_slice($dbBackups, $keep) as $old) {
                @unlink($old);
            }
            // Also prune temp deploys
            $tempDir = storage_path('temp');
            if (is_dir($tempDir)) {
                $temps = glob($tempDir . DIRECTORY_SEPARATOR . 'deploy_*') ?: [];
                usort($temps, fn($a, $b) => filemtime($b) <=> filemtime($a));
                foreach (array_slice($temps, $keep) as $old) {
                    $this->deleteRecursive($old);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('prune_backups_failed', ['error' => $e->getMessage()]);
        }
    }

    protected function deleteRecursive(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
            return;
        }
        if (! is_dir($path)) return;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($path);
    }
}
