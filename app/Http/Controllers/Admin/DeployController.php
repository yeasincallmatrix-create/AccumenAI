<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeploymentLog;
use App\Services\DeploymentGitService;
use App\Services\DeploymentZipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeployController extends Controller
{
    public function index(Request $request): View
    {
        $gitService = app(DeploymentGitService::class);

        $isGitAvailable = $gitService->isGitAvailable();
        $currentHash = $isGitAvailable ? $gitService->currentCommitHash() : null;
        $currentBranch = $isGitAvailable ? $gitService->currentBranch() : null;
        $branches = $isGitAvailable ? $gitService->availableBranches() : [];

        $filterType = $request->query('type');
        $logsQuery = DeploymentLog::query()->orderByDesc('created_at')->orderByDesc('id');
        if (in_array($filterType, ['git', 'zip'], true)) {
            $logsQuery->where('type', $filterType);
        }
        $logs = $logsQuery->limit(50)->get();

        return view('admin.deploy.index', [
            'isGitAvailable' => $isGitAvailable,
            'gitAvailable' => $isGitAvailable,
            'currentHash' => $currentHash,
            'currentCommit' => $currentHash,
            'currentBranch' => $currentBranch,
            'branches' => $branches,
            'logs' => $logs,
            'filterType' => $filterType,
        ]);
    }

    public function gitDeploy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch' => ['required', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\/\.]+$/'],
        ]);

        $branch = $validated['branch'] ?? 'main';
        $adminId = $request->user('platform_admin')?->getKey() ?? $request->user()?->getKey();

        $service = app(DeploymentGitService::class);
        $log = $service->deploy($branch, $adminId);

        if ($log->status === 'success') {
            return redirect()->route('admin.deploy.index')->with('status', "Git deployment to '{$branch}' completed. Commit: " . ($log->version ?? '—'));
        }

        return redirect()->route('admin.deploy.index')->with('error', 'Git deployment failed. Check log #' . $log->id);
    }

    public function zipDeploy(Request $request): RedirectResponse
    {
        $request->validate([
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:51200'], // 50MB = 51200KB
            'version' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('zip_file');
        $adminId = $request->user('platform_admin')?->getKey() ?? $request->user()?->getKey();
        $version = $request->input('version');

        $service = app(DeploymentZipService::class);
        $log = $service->deploy($file, $adminId, $version);

        if ($log->status === 'success') {
            return redirect()->route('admin.deploy.index')->with('status', 'ZIP deployment completed. Version: ' . ($log->version ?? $file->getClientOriginalName()));
        }

        return redirect()->route('admin.deploy.index')->with('error', 'ZIP deployment failed. Check log #' . $log->id);
    }

    public function rollback(Request $request, int $logId): RedirectResponse
    {
        $entry = DeploymentLog::findOrFail($logId);

        if ($entry->status === 'rolled_back') {
            return redirect()->route('admin.deploy.index')->with('error', 'This deployment has already been rolled back.');
        }

        $adminId = $request->user('platform_admin')?->getKey() ?? $request->user()?->getKey();

        if ($entry->type === 'git') {
            $service = app(DeploymentGitService::class);
            $result = $service->rollback($logId);
        } else {
            $service = app(DeploymentZipService::class);
            $result = $service->rollback($logId);
        }

        if ($result['success'] ?? false) {
            return redirect()->route('admin.deploy.index')->with('status', 'Rollback completed for deployment #' . $logId);
        }

        return redirect()->route('admin.deploy.index')->with('error', 'Rollback failed: ' . ($result['error'] ?? 'unknown error'));
    }
}
