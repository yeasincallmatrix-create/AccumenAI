<?php

namespace App\Services;

use App\Models\AuditLog;

/**
 * Step 47 — Guardian audit trail.
 *
 * Reuses the existing audit_logs table (user_type enum extended to include
 * 'guardian') so every important guardian action — login / logout / profile /
 * student views / attendance / results / fees / certificate / document
 * downloads — is traceable per institute + actor. Values are JSON snapshots,
 * never credentials or file contents.
 */
class GuardianAuditService
{
    public function record(
        int $instituteId,
        ?int $guardianId,
        string $action,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        AuditLog::create([
            'institute_id' => $instituteId,
            'user_type' => 'guardian',
            'user_id' => $guardianId,
            'action' => $action,
            'module' => 'guardian',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
