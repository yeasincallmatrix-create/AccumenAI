<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Education (assessment/exam/result) audit trail (Step 43).
 *
 * Reuses the existing audit_logs table with module='education' so every
 * assessment create / update / delete, component configuration change, marks
 * entry, lock / unlock and final-result lifecycle transition is traceable per
 * institute + actor. Values are JSON snapshots (never passwords or secrets);
 * the actor is the authenticated institute user id when known.
 */
class AcademicAssessmentAuditService
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
            'module' => 'education',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
