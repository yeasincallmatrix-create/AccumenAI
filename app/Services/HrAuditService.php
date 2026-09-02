<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * HR audit trail — reuses the existing audit_logs table (module='hr').
 *
 * Every department/designation/employee mutation is traceable per institute + actor.
 * Snapshots are JSON (never secrets); actor comes from the caller (authenticated institute_user).
 */
class HrAuditService
{
    public function record(
        int $instituteId,
        ?int $actorId,
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
            'module' => 'hr',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
