<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;
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
