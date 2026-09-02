<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Curriculum audit trail (Step 42).
 *
 * Reuses the existing audit_logs table with module='curricula' so every
 * create / update / status transition / module / lesson / material event is
 * traceable per institute + actor. Values are JSON snapshots (never passwords
 * or secrets); the actor is always the authenticated institute user.
 */
class CurriculumAuditService
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
            'module' => 'curricula',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
