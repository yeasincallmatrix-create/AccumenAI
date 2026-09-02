<?php

namespace App\Services\Alumni;

use App\Models\AuditLog;

/**
 * Step 48 — Alumni Management audit trail.
 *
 * Reuses the existing audit_logs table (same shape as BatchAuditService):
 * every alumni create / profile update / status transition / delete is logged
 * per institute + actor with JSON value snapshots. The actor is always the
 * authenticated institute user.
 */
class AlumniAuditService
{
    public function record(
        int $instituteId,
        int $actorId,
        string $action,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        AuditLog::create([
            'institute_id' => $instituteId,
            'user_type' => 'institute_user',
            'user_id' => $actorId,
            'action' => $action,
            'module' => 'alumni',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
