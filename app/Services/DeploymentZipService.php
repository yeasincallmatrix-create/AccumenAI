<?php

namespace App\Services;

use App\Models\DeploymentLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class DeploymentZipService
{
    protected string $basePath;

    /**
     * Files/dirs to exclude when merging code over existing installation.
     */
    protected array $excludeMerge = ['.env', '.git', 'storage', 'vendor', 'node_modules'];

    public function __construct()
    {
        $this->basePath = base_path();
    }

    /**
     * Validate and extract ZIP to storage/temp/deploy_{timestamp}/
     *
     * @throws \RuntimeException
     */
    public function uploadAndExtract(UploadedFile $file): string
    {
        // Validate extension
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'zip') {
            throw new \RuntimeException('Only .zip files are allowed.');
        }
        // Validate size 50MB
        if ($file->getSize() > 50 * 1024 * 1024) {
            throw new \RuntimeException('File size exceeds 50MB limit.');
        }

        $timestamp = date('Ymd_His') . '_' . uniqid();
        $extractPath = storage_path('temp/deploy_' . $timestamp);

        if (! is_dir($extractPath)) {
            @mkdir($extractPath, 0755, true);
        }

        $zipPath = $file->getPathname();

        $zip = new ZipArchive();
        $res = $zip->open($zipPath);
        if ($res !== true) {
            throw new \RuntimeException('Failed to open ZIP file. Error code: ' . $res);
        }

        // Security: prevent ZipSlip
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) continue;
            // Block absolute paths and directory traversal
            if (str_starts_with($name, '/') || str_contains($name, '..')) {
                $zip->close();
                throw new \RuntimeException('ZIP contains invalid paths: ' . $name);
            }
        }

        if (! $zip->extractTo($extractPath)) {
            $zip->close();
            throw new \RuntimeException('Failed to extract ZIP file.');
        }
        $zip->close();

        // If ZIP contains single top-level folder, use that as root
        $extractedRoot = $this->resolveExtractedRoot($extractPath);

        return $extractedRoot;
    }

    /**
     * Create backup of current code and DB.
     * Returns [backup_path, db_backup_path, logFragment]
     */
    public function createBackup(): array
    {
        $timestamp = date('Ymd_His');
        $codeBackupPath = storage_path('backups/code_' . $timestamp);
        $dbBackupPath = storage_path('backups/db_' . $timestamp . '.sql');
        $log = '';

        try {
            if (! is_dir($codeBackupPath)) {
                @mkdir($codeBackupPath, 0755, true);
            }
            $log .= $this->copyCodeForBackup($this->basePath, $codeBackupPath);
            $log .= "[Backup] Code backup created at: {$codeBackupPath}\n";
        } catch (\Throwable $e) {
            $log .= "[Backup] Code backup failed: " . $e->getMessage() . "\n";
            Log::warning('zip_deploy_backup_failed', ['error' => $e->getMessage()]);
        }

        try {
            $log .= $this->exportDatabase($dbBackupPath);
        } catch (\Throwable $e) {
            $log .= "[DB Backup] Failed: " . $e->getMessage() . "\n";
        }

        $this->pruneOldBackups();

        return [$codeBackupPath, $dbBackupPath, $log];
    }

    /**
     * Merge extracted code over existing installation.
     * Excludes .env, storage/, vendor/, node_modules/
     */
    public function mergeCode(string $extractedPath): string
    {
        $log = "[Merge] Merging code from {$extractedPath} to {$this->basePath}\n";
        $log .= "[Merge] Excluding: " . implode(', ', $this->excludeMerge) . "\n";

        if (! is_dir($extractedPath)) {
            throw new \RuntimeException("Extracted path not found: {$extractedPath}");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractedPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $copied = 0;
        $skipped = 0;
        foreach ($iterator as $item) {
            $rel = substr($item->getPathname(), strlen($extractedPath) + 1);
            // Normalize to forward slashes for comparison
            $relNorm = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
            $topLevel = explode('/', $relNorm)[0] ?? '';

            // Check if rel is in excluded list
            $shouldSkip = false;
            foreach ($this->excludeMerge as $ex) {
                $exNorm = trim($ex, '/');
                if ($relNorm === $exNorm || str_starts_with($relNorm, $exNorm . '/')) {
                    $shouldSkip = true;
                    break;
                }
                // For .env, match .env and .env.* except we already skip .env file itself
                if ($exNorm === '.env' && str_starts_with($relNorm, '.env')) {
                    $shouldSkip = true;
                    break;
                }
            }
            if ($shouldSkip) {
                $skipped++;
                continue;
            }

            $target = $this->basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relNorm);

            if ($item->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                @mkdir(dirname($target), 0755, true);
                if (@copy($item->getPathname(), $target)) {
                    $copied++;
                } else {
                    $log .= "[Merge] Failed to copy: {$relNorm}\n";
                }
            }
        }

        $log .= "[Merge] Completed: {$copied} files copied, {$skipped} excluded.\n";
        return $log;
    }

    /**
     * Run artisan/composer commands after merge.
     */
    public function runArtisanCommands(): string
    {
        $log = '';
        // Corrected for 2GB cPanel optimization: clear BEFORE cache, no destructive clear after. Add event:cache; handle route duplicate fallback.
        $commands = [
            'composer install --no-interaction --prefer-dist --optimize-autoloader 2>&1' => 300,
            'php artisan migrate --force 2>&1' => 300,
            'php artisan optimize:clear 2>&1' => 120,
            'php artisan config:cache 2>&1' => 120,
            'php artisan view:cache 2>&1' => 120,
            'php artisan event:cache 2>&1' => 120,
        ];
        foreach ($commands as $cmd => $timeout) {
            $log .= "\n[Artisan] {$cmd}\n";
            $out = @shell_exec($cmd);
            $log .= ($out !== null ? (string) $out : '[no output]') . "\n";
        }
        // Route cache with fallback for duplicate name (admin.certificates.requests-columns)
        $log .= "\n[Artisan] php artisan route:cache 2>&1 (with fallback)\n";
        $routeOut = @shell_exec('php artisan route:cache 2>&1');
        $routeOut = $routeOut !== null ? (string) $routeOut : '[no output]';
        $log .= $routeOut . "\n";
        if (str_contains(strtolower($routeOut), 'unable to prepare route') || str_contains(strtolower($routeOut), 'logicexception') || str_contains(strtolower($routeOut), 'already been assigned')) {
            $log .= "[Artisan] Route cache failed — falling back to route:clear\n";
            $fallback = @shell_exec('php artisan route:clear 2>&1');
            $log .= ($fallback !== null ? (string) $fallback : '[no output]') . "\n";
        }
        return $log;
    }

    /**
     * Full ZIP deploy pipeline.
     */
    public function deploy(UploadedFile $file, ?int $adminUserId = null, ?string $version = null): DeploymentLog
    {
        $fullLog = '';
        $status = 'success';
        $extractedPath = null;
        $backupPath = null;
        $dbBackupPath = null;

        try {
            // Validate file
            $fullLog .= "[ZIP] Validating upload...\n";
            $fullLog .= "[ZIP] File: " . $file->getClientOriginalName() . " (" . round($file->getSize() / 1024, 2) . " KB)\n";

            // Backup first
            [$backupPath, $dbBackupPath, $backupLog] = $this->createBackup();
            $fullLog .= $backupLog;

            // Extract
            $fullLog .= "[ZIP] Extracting...\n";
            $extractedPath = $this->uploadAndExtract($file);
            $fullLog .= "[ZIP] Extracted to: {$extractedPath}\n";

            // Merge
            $fullLog .= $this->mergeCode($extractedPath);

            // Artisan
            $fullLog .= $this->runArtisanCommands();

            $fullLog .= "\n[Deploy] ZIP deployment completed successfully.\n";

            // Cleanup temp extracted — keep for debugging? remove after success but keep 5
            // Do not delete immediately; pruneOldBackups handles it

            $version = $version ?? $file->getClientOriginalName();
        } catch (\Throwable $e) {
            $status = 'failed';
            $fullLog .= "\n[Error] ZIP deployment failed: " . $e->getMessage() . "\n";
            Log::error('zip_deploy_failed', ['error' => $e->getMessage()]);
        }

        return DeploymentLog::create([
            'admin_user_id' => $adminUserId,
            'type' => 'zip',
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
     * Rollback to backup state.
     */
    public function rollback(int $logId): array
    {
        $logEntry = DeploymentLog::findOrFail($logId);
        $output = '';

        try {
            $codePath = $logEntry->code_backup_path ?? $logEntry->backup_path;
            if ($codePath && is_dir($codePath)) {
                $output .= "[Rollback] Restoring code from: {$codePath}\n";
                $output .= $this->restoreCodeFromBackup($codePath);
            } else {
                $output .= "[Rollback] No code backup found at: " . ($logEntry->backup_path ?? 'null') . "\n";
                throw new \RuntimeException('Code backup not found, cannot rollback.');
            }

            if ($logEntry->db_backup_path && is_file($logEntry->db_backup_path)) {
                $output .= "[Rollback] Restoring DB from: {$logEntry->db_backup_path}\n";
                $output .= $this->importDatabase($logEntry->db_backup_path);
            } else {
                $output .= "[Rollback] No DB backup found, skipping DB restore.\n";
            }

            $output .= @shell_exec('php artisan config:clear 2>&1') ?? '';
            $output .= @shell_exec('php artisan cache:clear 2>&1') ?? '';

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

    protected function resolveExtractedRoot(string $extractPath): string
    {
        $items = @scandir($extractPath);
        if ($items === false) return $extractPath;
        $items = array_values(array_filter($items, fn($i) => !in_array($i, ['.', '..'])));
        if (count($items) === 1) {
            $single = $extractPath . DIRECTORY_SEPARATOR . $items[0];
            if (is_dir($single)) {
                // Heuristic: if single folder contains typical laravel structure or many files, use it
                return $single;
            }
        }
        return $extractPath;
    }

    protected function copyCodeForBackup(string $source, string $dest): string
    {
        $exclude = ['.git', 'storage', 'vendor', 'node_modules'];
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
            if (is_file($c)) return $c;
        }
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
            usort($codeBackups, fn($a, $b) => filemtime($b) <=> filemtime($a));
            usort($dbBackups, fn($a, $b) => filemtime($b) <=> filemtime($a));
            foreach (array_slice($codeBackups, $keep) as $old) {
                $this->deleteRecursive($old);
            }
            foreach (array_slice($dbBackups, $keep) as $old) {
                @unlink($old);
            }
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
