<?php

namespace App\Services\Backup;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2D: per-tenant mutual exclusion for all mutating backup operations
 * (backup + restore + GC). One key per tenant → the three operation types
 * exclude each other.
 *
 * Fail-fast by default (lock_wait_seconds = 0): a second dispatch is rejected
 * immediately with a clear message instead of blocking the queue.
 */
class TenantLockService
{
    /**
     * Try to acquire the tenant's operation lock.
     * Returns the Lock instance on success, null on failure.
     */
    public function acquire(int $tenantId, string $operation = 'backup'): ?Lock
    {
        $key = $this->key($tenantId);
        $ttl = (int) config('backup.lock_ttl_seconds', 3660);
        $wait = (int) config('backup.lock_wait_seconds', 0);

        try {
            $lock = Cache::lock($key, $ttl);

            if ($wait === 0) {
                // Fail-fast mode → don't block
                if (!$lock->get()) {
                    Log::warning('Tenant lock already held', [
                        'tenant_id' => $tenantId,
                        'operation' => $operation,
                        'key'       => $key,
                    ]);
                    return null;
                }
            } else {
                // Blocking mode with timeout
                if (!$lock->block($wait)) {
                    Log::warning('Tenant lock wait timeout', [
                        'tenant_id' => $tenantId,
                        'operation' => $operation,
                        'wait'      => $wait,
                    ]);
                    return null;
                }
            }

            Log::info('Tenant lock acquired', [
                'tenant_id' => $tenantId,
                'operation' => $operation,
            ]);

            return $lock;
        } catch (\Throwable $e) {
            Log::error('Tenant lock acquire failed', [
                'tenant_id' => $tenantId,
                'operation' => $operation,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function release(?Lock $lock, int $tenantId, string $operation = 'backup'): void
    {
        if (!$lock) {
            return;
        }

        try {
            $lock->release();
            Log::info('Tenant lock released', [
                'tenant_id' => $tenantId,
                'operation' => $operation,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Tenant lock release failed', [
                'tenant_id' => $tenantId,
                'operation' => $operation,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Non-destructive peek: acquires a 1-second lock and releases it
     * immediately when free — never leaves a stale lock behind.
     */
    public function isLocked(int $tenantId): bool
    {
        try {
            $lock = Cache::lock($this->key($tenantId), 1);
            if ($lock->get()) {
                $lock->release();
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('Tenant lock peek failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            // Assume locked on error → conservative fail-fast
            return true;
        }
    }

    private function key(int $tenantId): string
    {
        return "tenant:backup:operation:{$tenantId}";
    }
}
