<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionDashboardController extends Controller
{
    use ResolvesInstitute;

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.accounting.production.index', [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
            'systemHealth' => $this->getSystemHealth(),
            'queueStatus' => $this->getQueueStatus(),
            'recentErrors' => $this->getRecentErrors(),
            'diskUsage' => $this->getDiskUsage(),
        ]);
    }

    public function performance(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.accounting.production.performance', [
            'institute' => $institute,
            'branch' => $this->actingBranch($request),
            'slowQueries' => $this->getSlowQueryIndicators(),
            'cacheMetrics' => $this->getCacheMetrics(),
            'appMetrics' => $this->getApplicationMetrics(),
        ]);
    }

    private function getSystemHealth(): array
    {
        $checks = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Exception $e) {
            $checks['database'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        try {
            $key = 'prod_health_' . uniqid();
            \Illuminate\Support\Facades\Cache::put($key, 1, 10);
            \Illuminate\Support\Facades\Cache::forget($key);
            $checks['cache'] = ['status' => 'ok', 'message' => 'Operational'];
        } catch (\Exception $e) {
            $checks['cache'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $checks['queue'] = ['status' => 'ok', 'message' => config('queue.default', 'sync')];

        return $checks;
    }

    private function getQueueStatus(): array
    {
        try {
            $hasJobs = DB::getSchemaBuilder()->hasTable('jobs');
            $pending = $hasJobs ? DB::table('jobs')->count() : 0;
            $failed = DB::getSchemaBuilder()->hasTable('failed_jobs')
                ? DB::table('failed_jobs')->count()
                : 0;

            return ['pending' => $pending, 'failed' => $failed];
        } catch (\Exception $e) {
            return ['pending' => 0, 'failed' => 0, 'error' => $e->getMessage()];
        }
    }

    private function getRecentErrors(): array
    {
        try {
            $logPath = storage_path('logs/laravel.log');
            if (! file_exists($logPath)) {
                return [];
            }

            $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $errors = [];
            $pattern = '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*(?:ERROR|CRITICAL)/';

            for ($i = count($lines) - 1; $i >= 0 && count($errors) < 10; $i--) {
                if (preg_match($pattern, $lines[$i], $matches)) {
                    $errors[] = ['time' => $matches[1], 'message' => mb_substr($lines[$i], 0, 200)];
                }
            }

            return array_reverse($errors);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getDiskUsage(): array
    {
        $path = base_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false) {
            return ['total_gb' => 0, 'free_gb' => 0, 'used_percent' => 0];
        }

        $used = $total - $free;

        return [
            'total_gb' => round($total / (1024 * 1024 * 1024), 2),
            'free_gb' => round($free / (1024 * 1024 * 1024), 2),
            'used_percent' => round(($used / $total) * 100, 1),
        ];
    }

    private function getSlowQueryIndicators(): array
    {
        try {
            $tableExists = DB::getSchemaBuilder()->hasTable('slow_queries');
            if (! $tableExists) {
                return ['available' => false, 'message' => 'No slow query log table found'];
            }

            $recentSlow = DB::table('slow_queries')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            return ['available' => true, 'queries' => $recentSlow];
        } catch (\Exception $e) {
            return ['available' => false, 'message' => $e->getMessage()];
        }
    }

    private function getCacheMetrics(): array
    {
        return [
            'driver' => config('cache.default', 'file'),
            'prefix' => config('cache.prefix', ''),
        ];
    }

    private function getApplicationMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'debug' => config('app.debug'),
            'timezone' => config('app.timezone'),
        ];
    }
}
