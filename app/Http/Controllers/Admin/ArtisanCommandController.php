<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommandLog;
use App\Models\DeploymentLog;
use App\Models\PlatformAuditLog;
use App\Services\DeploymentGitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class ArtisanCommandController extends Controller
{
    /**
     * Predefined safe commands - whitelist only.
     * Key is actual artisan command, value is metadata.
     */
    protected array $allowedCommands = [
        'cache:clear' => ['label' => 'Clear Application Cache', 'description' => 'Clears all application caches.', 'risk' => 'low'],
        'route:cache' => ['label' => 'Cache Routes', 'description' => 'Cache the routes for faster routing.', 'risk' => 'medium'],
        'config:cache' => ['label' => 'Cache Config', 'description' => 'Cache the configuration files.', 'risk' => 'medium'],
        'view:cache' => ['label' => 'Cache Views', 'description' => 'Cache the compiled views.', 'risk' => 'low'],
        'view:clear' => ['label' => 'Clear Compiled Views', 'description' => 'Clear all compiled view files.', 'risk' => 'low'],
        'optimize:clear' => ['label' => 'Clear Optimization', 'description' => 'Clear all cached optimization files.', 'risk' => 'low'],
        'queue:restart' => ['label' => 'Restart Queue Workers', 'description' => 'Restart the queue workers.', 'risk' => 'low'],
        'backup:run' => ['label' => 'Run Backup', 'description' => 'Create a new application backup.', 'risk' => 'high'],
        'config:clear' => ['label' => 'Clear Config Cache', 'description' => 'Remove the configuration cache file.', 'risk' => 'low'],
        'route:clear' => ['label' => 'Clear Route Cache', 'description' => 'Remove the route cache file.', 'risk' => 'low'],
        'optimize' => ['label' => 'Optimize Application', 'description' => 'Cache framework bootstrap files.', 'risk' => 'medium'],
        'migrate:status' => ['label' => 'Migration Status', 'description' => 'Show the status of each migration.', 'risk' => 'low'],
        'storage:link' => ['label' => 'Storage Link', 'description' => 'Create the symbolic link for storage.', 'risk' => 'low'],
    ];

    public function index(Request $request): View
    {
        // Fetch recent logs for display (last 20)
        $recentLogs = CommandLog::query()
            ->with('admin')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Deployment data for unified view (instance-based service)
        $gitService = app(DeploymentGitService::class);
        $gitAvailable = $gitService->isGitAvailable();
        $currentCommit = $gitAvailable ? ($gitService->currentCommitHash() ?? (method_exists($gitService, 'currentCommit') ? $gitService->currentCommit() : null)) : null;
        $currentBranch = $gitAvailable ? $gitService->currentBranch() : null;
        $deploymentLogs = DeploymentLog::query()->with('admin')->orderByDesc('created_at')->limit(10)->get();

        return view('admin.artisan-commands.index', [
            'commands' => $this->allowedCommands,
            'recentLogs' => $recentLogs,
            'gitAvailable' => $gitAvailable,
            'currentCommit' => $currentCommit,
            'currentBranch' => $currentBranch,
            'deploymentLogs' => $deploymentLogs,
        ]);
    }

    public function execute(Request $request): JsonResponse
    {
        // Rate limiting: max 10 per hour per admin
        $admin = $request->user('platform_admin') ?? $request->user();
        $adminId = $admin?->getKey() ?? $request->ip();
        $rateKey = 'artisan-command:' . $adminId;

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            $seconds = RateLimiter::availableIn($rateKey);
            return response()->json([
                'success' => false,
                'message' => 'Too many attempts. Please try again in ' . ceil($seconds / 60) . ' minute(s).',
                'retry_after' => $seconds,
            ], 429);
        }
        RateLimiter::hit($rateKey, 3600);

        $validated = $request->validate([
            'command' => ['required', 'string'],
        ]);

        $command = $validated['command'];

        // Strict whitelist check - never trust user input
        if (! array_key_exists($command, $this->allowedCommands)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or disallowed command.',
            ], 422);
        }

        $meta = $this->allowedCommands[$command];

        $output = '';
        $exitCode = 0;
        $success = true;

        try {
            // Use Artisan::call - never exec() with user input
            $exitCode = Artisan::call($command);
            $output = Artisan::output();

            // Fallback if output empty but succeeded
            if (trim($output) === '' && $exitCode === 0) {
                $output = 'Command "' . $command . '" executed successfully (no output).';
            } elseif (trim($output) === '' && $exitCode !== 0) {
                $output = 'Command "' . $command . '" failed with exit code ' . $exitCode . '.';
                $success = false;
            } elseif ($exitCode !== 0) {
                $success = false;
            }
        } catch (\Throwable $e) {
            $output = 'Error executing "' . $command . '": ' . $e->getMessage();
            $success = false;
            $exitCode = 1;
        }

        // Truncate output for log storage (summary)
        $outputSummary = mb_substr(trim($output), 0, 2000);
        if (mb_strlen(trim($output)) > 2000) {
            $outputSummary .= '... [truncated]';
        }

        // Audit log to command_logs table
        try {
            CommandLog::create([
                'admin_id' => $admin?->getKey(),
                'command' => $command,
                'label' => $meta['label'] ?? $command,
                'risk' => $meta['risk'] ?? 'low',
                'output_summary' => $outputSummary,
                'full_output' => trim($output),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'exit_code' => $exitCode,
                'success' => $success,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('command_log_failed', ['error' => $e->getMessage(), 'command' => $command]);
        }

        // Also log to platform_audit_logs for centralized audit
        try {
            PlatformAuditLog::record('artisan', $command, 'executed', [
                'command' => $command,
                'label' => $meta['label'] ?? $command,
                'risk' => $meta['risk'] ?? 'low',
                'exit_code' => $exitCode,
                'success' => $success,
                'output_summary' => mb_substr($outputSummary, 0, 500),
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // silent
        }

        return response()->json([
            'success' => $success,
            'command' => $command,
            'label' => $meta['label'],
            'output' => trim($output),
            'exit_code' => $exitCode,
        ], $success ? 200 : 500);
    }

    /**
     * Expose allowed commands for testing / API (read-only).
     */
    public function getAllowedCommands(): array
    {
        return $this->allowedCommands;
    }
}
