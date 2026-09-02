<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = ! in_array(false, array_column($checks, 'healthy'), true);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');

            return ['healthy' => true, 'message' => 'Database connection OK'];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health_check_' . uniqid();
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return $value === 'ok'
                ? ['healthy' => true, 'message' => 'Cache read/write OK']
                : ['healthy' => false, 'message' => 'Cache value mismatch'];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => 'Cache check failed: ' . $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $tableExists = DB::getSchemaBuilder()->hasTable('jobs');

            return ['healthy' => true, 'message' => $tableExists ? 'Queue table exists' : 'Queue table missing (sync driver?)'];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => 'Queue check failed: ' . $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = Storage::disk('local')->path('/');
            $free = @disk_free_space($path);

            if ($free === false) {
                return ['healthy' => true, 'message' => 'Storage accessible (free space unknown)'];
            }

            $freeGb = round($free / (1024 * 1024 * 1024), 2);

            return [
                'healthy' => $freeGb > 1,
                'message' => "Disk free: {$freeGb} GB",
                'free_bytes' => $free,
            ];
        } catch (\Exception $e) {
            return ['healthy' => false, 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }
}
