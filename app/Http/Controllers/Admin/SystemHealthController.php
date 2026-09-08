<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

/**
 * Super Admin system health dashboard: RAM / storage / cache visibility
 * plus guarded cleanup actions. Destructive routes require the
 * `admin.system` permission (PlatformAdmin bypasses via CheckPermission).
 */
class SystemHealthController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.system', only: ['index', 'clearCache', 'clearTempFiles']),
        ];
    }

    public function index(): View
    {
        return view('admin.system-health.index', [
            'ram' => $this->ramUsage(),
            'disk' => $this->diskUsage(),
            'accountRam' => $this->accountRamUsage(),
            'accountDisk' => $this->accountDiskUsage(),
            'cacheStatus' => $this->cacheStatus(),
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'memory_limit' => ini_get('memory_limit'),
            ],
        ]);
    }

    public function clearCache(): RedirectResponse
    {
        Artisan::call('optimize:clear');
        $output = trim((string) Artisan::output());

        return redirect()->route('admin.system-health.index')
            ->with('status', 'All caches cleared successfully.' . ($output !== '' ? ' Output: ' . $output : ''));
    }

    public function clearTempFiles(): RedirectResponse
    {
        $paths = [
            storage_path('framework/cache'),
            storage_path('temp'),
        ];

        $deleted = 0;
        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }
            foreach (File::allFiles($path) as $file) {
                // Never remove dotfiles (e.g. .gitignore keeps empty dirs tracked).
                if (str_starts_with($file->getFilename(), '.')) {
                    continue;
                }
                if (@unlink($file->getPathname())) {
                    $deleted++;
                }
            }
        }

        return redirect()->route('admin.system-health.index')
            ->with('status', "Cleared {$deleted} temporary file(s).");
    }

    /**
     * @return array{total: float, used: float, percent: int, unit: string}
     */
    private function ramUsage(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->windowsRamUsage();
        }

        $data = @file_get_contents('/proc/meminfo');
        if (! is_string($data)
            || ! preg_match('/MemTotal:\s+(\d+)/', $data, $total)
            || ! preg_match('/MemAvailable:\s+(\d+)/', $data, $available)
            || (int) $total[1] <= 0) {
            return ['total' => 0, 'used' => 0, 'percent' => 0, 'unit' => 'MB'];
        }

        $used = (int) $total[1] - (int) $available[1];

        return [
            'total' => round((int) $total[1] / 1024, 2),
            'used' => round($used / 1024, 2),
            'percent' => (int) round(($used / (int) $total[1]) * 100),
            'unit' => 'MB',
        ];
    }

    /** @return array{total: float, used: float, percent: int, unit: string} */
    private function windowsRamUsage(): array
    {
        $out = [];
        // wmic is removed on recent Windows; use PowerShell CIM instead.
        @exec('powershell -NoProfile -Command "(Get-CimInstance Win32_OperatingSystem | Select-Object TotalVisibleMemorySize,FreePhysicalMemory | ConvertTo-Json -Compress)" 2>NUL', $out);
        $mem = json_decode(implode('', $out), true);
        $total = (int) ($mem['TotalVisibleMemorySize'] ?? 0);
        $free = (int) ($mem['FreePhysicalMemory'] ?? 0);
        if ($total <= 0) {
            return ['total' => 0, 'used' => 0, 'percent' => 0, 'unit' => 'MB'];
        }

        $used = $total - $free;

        return [
            'total' => round($total / 1024, 2),
            'used' => round($used / 1024, 2),
            'percent' => (int) round(($used / $total) * 100),
            'unit' => 'MB',
        ];
    }

    /** @return array{total: float, used: float, percent: int, unit: string, path: string} */
    private function diskUsage(): array
    {
        $path = base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if ($total === false || $free === false || $total <= 0) {
            return ['total' => 0, 'used' => 0, 'percent' => 0, 'unit' => 'GB', 'path' => $path];
        }

        $used = $total - $free;

        return [
            'total' => round($total / 1024 / 1024 / 1024, 2),
            'used' => round($used / 1024 / 1024 / 1024, 2),
            'percent' => (int) round(($used / $total) * 100),
            'unit' => 'GB',
            'path' => $path,
        ];
    }

    /** @return array<string, mixed> */
    private function accountRamUsage(): array
    {
        $limit = $this->parsePhpMemoryLimit((string) ini_get('memory_limit'));
        $usedMb = round(memory_get_usage(true) / 1024 / 1024, 2);

        return [
            'limit' => $limit,          // MB, null = unlimited
            'used' => $usedMb,
            'percent' => $limit !== null && $limit > 0 ? min(100, (int) round(($usedMb / $limit) * 100)) : 0,
        ];
    }

    /** @return int|null bytes, null = unlimited */
    private function parsePhpMemoryLimit(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return null;
        }
        if (! preg_match('/^(\d+(?:\.\d+)?)\s*([kmg]?)/i', $value, $m)) {
            return null;
        }
        $bytes = (float) $m[1];
        $bytes *= match (strtolower($m[2])) {
            'g' => 1024 * 1024 * 1024,
            'm' => 1024 * 1024,
            'k' => 1024,
            default => 1,
        };

        return (int) round($bytes / 1024 / 1024);
    }

    /**
     * Account storage: home-directory size vs the hosting quota.
     *
     * Home defaults to the parent of the app root (…/public_html on cPanel);
     * quota defaults to 2048 MB. Override per environment:
     *   ACCOUNT_HOME_PATH=/home/accumena  ACCOUNT_QUOTA_MB=2048
     *
     * Directory walks are cached for 10 minutes and capped so a huge home
     * cannot stall the dashboard; truncated scans are flagged.
     *
     * @return array{home: string, quota_mb: int, used_mb: float, percent: int, truncated: bool, measured_at: string}
     */
    private function accountDiskUsage(): array
    {
        $home = (string) (env('ACCOUNT_HOME_PATH') ?: dirname(base_path()));
        $quotaMb = max(1, (int) (env('ACCOUNT_QUOTA_MB', 2048)));

        $measured = Cache::remember('system-health:account-disk', 600, function () use ($home) {
            return $this->measureDirectory($home);
        });

        $usedMb = round($measured['bytes'] / 1024 / 1024, 2);

        return [
            'home' => $home,
            'quota_mb' => $quotaMb,
            'used_mb' => $usedMb,
            'percent' => min(100, (int) round(($usedMb / $quotaMb) * 100)),
            'over_quota' => $usedMb > $quotaMb,
            'truncated' => $measured['truncated'],
            'measured_at' => $measured['measured_at'],
        ];
    }

    /** @return array{bytes: int, truncated: bool, measured_at: string} */
    private function measureDirectory(string $path): array
    {
        $result = ['bytes' => 0, 'truncated' => false, 'measured_at' => now()->toDateTimeString()];

        if (! is_dir($path) || ! is_readable($path)) {
            return $result;
        }

        // Fast path on Linux when exec() is available.
        if (PHP_OS_FAMILY !== 'Windows' && function_exists('exec')) {
            $out = [];
            @exec('du -sb '.escapeshellarg($path).' 2>/dev/null', $out);
            if (isset($out[0]) && preg_match('/^(\d+)\s/', (string) $out[0], $m)) {
                $result['bytes'] = (int) $m[1];

                return $result;
            }
        }

        // Portable fallback: capped recursive walk (symlinks skipped).
        $count = 0;
        $cap = 100000;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->isLink()) {
                    continue;
                }
                $result['bytes'] += $file->getSize();
                if (++$count >= $cap) {
                    $result['truncated'] = true;
                    break;
                }
            }
        } catch (\Throwable) {
            $result['truncated'] = true;
        }

        return $result;
    }

    /** @return array<string, array{count: int, size: float}> */
    private function cacheStatus(): array
    {
        $folders = [
            'bootstrap/cache' => base_path('bootstrap/cache'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/views' => storage_path('framework/views'),
        ];

        $status = [];
        foreach ($folders as $name => $path) {
            $files = File::isDirectory($path) ? File::allFiles($path) : [];
            $size = 0;
            foreach ($files as $file) {
                $size += $file->getSize();
            }
            $status[$name] = ['count' => count($files), 'size' => round($size / 1024, 2)];
        }

        return $status;
    }
}
