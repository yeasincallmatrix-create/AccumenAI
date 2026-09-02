<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Teacher management audit trail (Step 36).
 *
 * Reuses the existing audit_logs table with module='teachers' so every create /
 * update / status / branch / assignment event is traceable per institute +
 * actor. Values are JSON snapshots (never passwords, API keys or other
 * secrets); the actor is always the authenticated institute user.
 */
class TeacherAuditService
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
            'module' => 'teachers',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
