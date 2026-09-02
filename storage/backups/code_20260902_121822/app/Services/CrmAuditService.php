<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * CRM audit trail. Reuses the existing audit_logs table (legacy schema, no
 * migration) with module='crm' so every create / update / delete / assign /
 * convert / task-complete event is traceable per institute + actor.
 *
 * Values are JSON snapshots (never raw secrets). The actor is always the
 * authenticated institute user; institute identity comes from the caller.
 */
class CrmAuditService
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
            'module' => 'crm',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
