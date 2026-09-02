<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Batch / academic-session management audit trail (STEP 41).
 *
 * Reuses the existing audit_logs table. Batches, academic years and related
 * lifecycle actions are logged per institute + actor so every create / update /
 * status transition / enrollment / transfer is traceable. Values are JSON
 * snapshots (never passwords, API keys or other secrets); the actor is always
 * the authenticated institute user.
 */
class BatchAuditService
{
    public function record(
        int $instituteId,
        int $actorId,
        string $action,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        string $module = 'batches',
    ): void {
        AuditLog::create([
            'institute_id' => $instituteId,
            'user_type' => 'institute_user',
            'user_id' => $actorId,
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
