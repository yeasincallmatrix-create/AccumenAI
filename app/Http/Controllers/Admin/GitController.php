<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DeploymentGitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GitController extends Controller
{
    protected DeploymentGitService $gitService;

    public function __construct(DeploymentGitService $gitService)
    {
        $this->gitService = $gitService;
    }

    public function index(): View
    {
        $isGitAvailable = $this->gitService->isGitAvailable();
        $currentBranch = $isGitAvailable ? $this->gitService->currentBranch() : null;
        $currentHash = $isGitAvailable ? $this->gitService->currentCommitHash() : null;
        $branches = $isGitAvailable ? $this->gitService->availableBranches() : [];
        $localBranches = $isGitAvailable ? $this->localBranches() : [];
        $remotes = $isGitAvailable ? $this->remotesList() : [];
        $stashList = $isGitAvailable ? $this->stashListData() : [];
        $status = $isGitAvailable ? $this->gitStatusData() : null;
        $gitLog = $isGitAvailable ? $this->gitLogData() : [];
        $hasChanges = $status !== null && (
            trim($status['modified'] ?? '') !== '' ||
            trim($status['staged'] ?? '') !== '' ||
            trim($status['untracked'] ?? '') !== ''
        );

        return view('admin.deploy.git', [
            'isGitAvailable' => $isGitAvailable,
            'currentBranch' => $currentBranch,
            'currentHash' => $currentHash,
            'branches' => $branches,
            'localBranches' => $localBranches,
            'remotes' => $remotes,
            'stashList' => $stashList,
            'status' => $status,
            'gitLog' => $gitLog,
            'hasChanges' => $hasChanges,
        ]);
    }

    public function status(): JsonResponse
    {
        if (! $this->gitService->isGitAvailable()) {
            return response()->json(['error' => 'Git not available'], 500);
        }
        return response()->json($this->gitStatusData());
    }

    public function log(Request $request): JsonResponse
    {
        if (! $this->gitService->isGitAvailable()) {
            return response()->json(['error' => 'Git not available'], 500);
        }
        $count = min((int) $request->query('count', 30), 100);
        return response()->json($this->gitLogData($count));
    }

    public function diff(Request $request): JsonResponse
    {
        if (! $this->gitService->isGitAvailable()) {
            return response()->json(['error' => 'Git not available'], 500);
        }
        $file = $request->query('file');
        $cached = $request->boolean('cached', false);
        return response()->json(['diff' => $this->gitDiffData($file, $cached)]);
    }

    public function pull(Request $request): RedirectResponse
    {
        $branch = $request->input('branch', $this->gitService->currentBranch() ?? 'main');
        $remote = $request->input('remote', 'origin');
        $output = $this->runGit("git pull {$remote} {$branch} 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Git Pull: " . trim($output));
    }

    public function push(Request $request): RedirectResponse
    {
        $branch = $request->input('branch', $this->gitService->currentBranch() ?? 'main');
        $remote = $request->input('remote', 'origin');
        $force = $request->boolean('force', false);
        $forceFlag = $force ? '--force' : '';
        $output = $this->runGit("git push {$remote} {$branch} {$forceFlag} 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Git Push: " . trim($output));
    }

    public function createBranch(Request $request): RedirectResponse
    {
        $name = $request->input('name');
        if (! $name || ! preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $name)) {
            return redirect()->route('admin.git.index')->with('error', 'Invalid branch name.');
        }
        $startPoint = $request->input('start_point', '');
        $cmd = $startPoint ? "git branch {$name} {$startPoint} 2>&1" : "git branch {$name} 2>&1";
        $output = $this->runGit($cmd);
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Create Branch: " . trim($output));
    }

    public function switchBranch(Request $request): RedirectResponse
    {
        $branch = $request->input('branch');
        if (! $branch || ! preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $branch)) {
            return redirect()->route('admin.git.index')->with('error', 'Invalid branch name.');
        }
        $output = $this->runGit("git checkout {$branch} 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Switch Branch: " . trim($output));
    }

    public function deleteBranch(Request $request): RedirectResponse
    {
        $branch = $request->input('branch');
        $force = $request->boolean('force', false);
        if (! $branch || ! preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $branch)) {
            return redirect()->route('admin.git.index')->with('error', 'Invalid branch name.');
        }
        $current = $this->gitService->currentBranch();
        if ($branch === $current) {
            return redirect()->route('admin.git.index')->with('error', 'Cannot delete the current branch. Switch to another branch first.');
        }
        $flag = $force ? '-D' : '-d';
        $output = $this->runGit("git branch {$flag} {$branch} 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Delete Branch: " . trim($output));
    }

    public function mergeBranch(Request $request): RedirectResponse
    {
        $branch = $request->input('branch');
        if (! $branch || ! preg_match('/^[a-zA-Z0-9_\-\/\.]+$/', $branch)) {
            return redirect()->route('admin.git.index')->with('error', 'Invalid branch name.');
        }
        $output = $this->runGit("git merge {$branch} 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Merge: " . trim($output));
    }

    public function stash(Request $request): RedirectResponse
    {
        $message = $request->input('message', '');
        $msgFlag = $message ? "-m " . escapeshellarg($message) : '';
        $output = $this->runGit("git stash save {$msgFlag} 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Stash: " . trim($output));
    }

    public function stashPop(): RedirectResponse
    {
        $output = $this->runGit("git stash pop 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Stash Pop: " . trim($output));
    }

    public function stashDrop(Request $request): RedirectResponse
    {
        $stashId = $request->input('stash_id', 'stash@{0}');
        $output = $this->runGit("git stash drop " . escapeshellarg($stashId) . " 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Stash Drop: " . trim($output));
    }

    public function reset(Request $request): RedirectResponse
    {
        $mode = $request->input('mode', 'soft');
        $commit = $request->input('commit', 'HEAD');
        $validModes = ['soft', 'mixed', 'hard'];
        if (! in_array($mode, $validModes, true)) {
            return redirect()->route('admin.git.index')->with('error', 'Invalid reset mode.');
        }
        $output = $this->runGit("git reset --{$mode} {$commit} 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Reset ({$mode}): " . trim($output));
    }

    public function addRemote(Request $request): RedirectResponse
    {
        $name = $request->input('name');
        $url = $request->input('url');
        if (! $name || ! $url) {
            return redirect()->route('admin.git.index')->with('error', 'Remote name and URL are required.');
        }
        $output = $this->runGit("git remote add " . escapeshellarg($name) . " " . escapeshellarg($url) . " 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Add Remote: " . trim($output));
    }

    public function removeRemote(Request $request): RedirectResponse
    {
        $name = $request->input('name');
        if (! $name) {
            return redirect()->route('admin.git.index')->with('error', 'Remote name is required.');
        }
        $output = $this->runGit("git remote remove " . escapeshellarg($name) . " 2>&1");
        $success = ! str_contains(strtolower($output), 'error') && ! str_contains(strtolower($output), 'fatal');
        return redirect()->route('admin.git.index')
            ->with($success ? 'status' : 'error', "Remove Remote: " . trim($output));
    }

    public function remotes(): JsonResponse
    {
        if (! $this->gitService->isGitAvailable()) {
            return response()->json(['error' => 'Git not available'], 500);
        }
        return response()->json($this->remotesList());
    }

    public function stashList(): JsonResponse
    {
        if (! $this->gitService->isGitAvailable()) {
            return response()->json(['error' => 'Git not available'], 500);
        }
        return response()->json($this->stashListData());
    }

    protected function localBranches(): array
    {
        $output = $this->runGit('git branch --list 2>&1');
        $branches = [];
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $line = preg_replace('/^\*?\s+/', '', $line);
            $branches[] = $line;
        }
        return array_values(array_filter($branches));
    }

    protected function remotesList(): array
    {
        $output = $this->runGit('git remote -v 2>&1');
        $remotes = [];
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if ($line === '' || ! preg_match('/^(\S+)\s+(\S+)\s+\((push|fetch)\)$/', $line, $m)) continue;
            $remotes[$m[1]][$m[3]] = $m[2];
        }
        return $remotes;
    }

    protected function stashListData(): array
    {
        $output = $this->runGit('git stash list 2>&1');
        $items = [];
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $items[] = $line;
        }
        return $items;
    }

    protected function gitStatusData(): array
    {
        $output = $this->runGit('git status --porcelain 2>&1');
        $staged = [];
        $modified = [];
        $untracked = [];
        $deleted = [];
        foreach (explode("\n", trim($output)) as $line) {
            if ($line === '') continue;
            $index = substr($line, 0, 1);
            $worktree = substr($line, 1, 1);
            $file = trim(substr($line, 3));
            if ($index !== ' ' && $index !== '?' && $index !== '!') {
                $staged[] = $file;
            }
            if ($worktree !== ' ' && $worktree !== '?') {
                if ($index === 'D' || $worktree === 'D') {
                    $deleted[] = $file;
                } else {
                    $modified[] = $file;
                }
            }
            if ($index === '?' && $worktree === '?') {
                $untracked[] = $file;
            }
        }
        return [
            'raw' => trim($output),
            'staged' => implode("\n", $staged),
            'modified' => implode("\n", $modified),
            'untracked' => implode("\n", $untracked),
            'deleted' => implode("\n", $deleted),
            'clean' => empty($staged) && empty($modified) && empty($untracked) && empty($deleted),
        ];
    }

    protected function gitLogData(int $count = 30): array
    {
        $format = '%H|%h|%an|%ai|%s';
        $output = $this->runGit("git log --pretty=format:\"{$format}\" -{$count} 2>&1");
        $entries = [];
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line, "\" \t\n\r\0\x0B");
            if ($line === '') continue;
            $parts = explode('|', $line, 5);
            if (count($parts) < 5) continue;
            $entries[] = [
                'hash' => $parts[0],
                'short' => $parts[1],
                'author' => $parts[2],
                'date' => $parts[3],
                'message' => $parts[4],
            ];
        }
        return $entries;
    }

    protected function gitDiffData(?string $file = null, bool $cached = false): string
    {
        $cachedFlag = $cached ? '--cached' : '';
        $fileArg = $file ? '-- ' . escapeshellarg($file) : '';
        return $this->runGit("git diff {$cachedFlag} {$fileArg} 2>&1");
    }

    protected function runShell(string $command, int $timeout = 120): string
    {
        $output = @shell_exec($command);
        return $output !== null ? (string) $output : "[no output]";
    }

    protected function runGit(string $command): string
    {
        return $this->runShell($command);
    }
}
