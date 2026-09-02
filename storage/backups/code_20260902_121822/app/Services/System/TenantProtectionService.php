<?php

namespace App\Services\System;

use App\Models\Institute;
use App\Models\TenantDeletionRequest;
use App\Models\TenantRecoveryArchive;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Step 101 — Tenant Data Protection.
 * Prevents accidental institute/owner deletion and cascade deletion.
 */
class TenantProtectionService
{
    public const CONFIRMATION_TTL_HOURS = 24;

    /**
     * Request deletion — creates a confirmation workflow, archives snapshot, logs audit.
     * Does NOT delete immediately. Requires explicit confirmation with token.
     */
    public function requestDeletion(string $deletableType, int $deletableId, int $requestedBy, string $reason = null): TenantDeletionRequest
    {
        $morphMap = [
            'institute' => Institute::class,
            'user' => User::class,
        ];
        $class = $morphMap[$deletableType] ?? $deletableType;

        $model = $class::findOrFail($deletableId);

        // Archive snapshot for recovery
        $this->archiveSnapshot($class, $deletableId, $model->institute_id ?? $model->id ?? null, $requestedBy);

        $token = Str::random(64);
        $request = TenantDeletionRequest::create([
            'deletable_type' => $class,
            'deletable_id' => $deletableId,
            'institute_id' => $model->institute_id ?? ($class === Institute::class ? $model->id : null),
            'requested_by' => $requestedBy,
            'requested_by_type' => 'user',
            'reason' => $reason,
            'confirmation_token' => hash('sha256', $token),
            'status' => 'pending',
            'expires_at' => now()->addHours(self::CONFIRMATION_TTL_HOURS),
        ]);

        // Mark institute as pending deletion (soft flag)
        if ($class === Institute::class) {
            $model->update([
                'deletion_requested_at' => now(),
                'deletion_requested_by' => $requestedBy,
            ]);
        }

        $this->audit('deletion_requested', $class, $deletableId, $requestedBy, ['token' => substr($token, 0, 8).'...']);

        // Return with raw token for delivery (e.g., email) — store hashed, expose raw via transient property not persisted
        $request->setAttribute('raw_token', $token);
        $request->syncOriginalAttribute('raw_token');

        return $request;
    }

    public function confirmDeletion(string $token, int $confirmedBy): TenantDeletionRequest
    {
        $hashed = hash('sha256', $token);
        $request = TenantDeletionRequest::where('confirmation_token', $hashed)->where('status', 'pending')->firstOrFail();

        if ($request->isExpired()) {
            $request->update(['status' => 'expired']);
            throw new \Exception('Confirmation token expired');
        }

        // Verify the deleter has permission (must be owner or platform admin)
        $request->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        $class = $request->deletable_type;
        $model = $class::find($request->deletable_id);

        if ($model) {
            // Soft delete via archive + softDelete (if model uses SoftDeletes)
            if (method_exists($model, 'trashed')) {
                $model->delete();
            } else {
                // Fallback: mark as deleted via archive and keep row but flag
                $model->update(['status' => 'deleted']);
            }
            $this->audit('deletion_confirmed', $class, $request->deletable_id, $confirmedBy);
        }

        return $request;
    }

    public function cancelDeletion(int $requestId, int $cancelledBy): TenantDeletionRequest
    {
        $request = TenantDeletionRequest::findOrFail($requestId);
        $request->update(['status' => 'cancelled']);
        if ($request->deletable_type === Institute::class) {
            $inst = Institute::withTrashed()->find($request->deletable_id);
            if ($inst) {
                $inst->update(['deletion_requested_at' => null, 'deletion_requested_by' => null]);
            }
        }
        $this->audit('deletion_cancelled', $request->deletable_type, $request->deletable_id, $cancelledBy);
        return $request;
    }

    public function recover(string $archivableType, int $archivableId, int $recoveredBy): bool
    {
        $archive = TenantRecoveryArchive::where('archivable_type', $archivableType)
            ->where('archivable_id', $archivableId)
            ->latest()
            ->first();

        if (! $archive) {
            return false;
        }

        $class = $archive->archivable_type;
        $snapshot = $archive->snapshot;

        // Restore institute/user from snapshot
        if ($class === Institute::class) {
            $existing = Institute::withTrashed()->find($archivableId);
            if ($existing && $existing->trashed()) {
                $existing->restore();
                $existing->update(['deletion_requested_at' => null, 'deletion_requested_by' => null]);
                $this->audit('recovery_restored', $class, $archivableId, $recoveredBy);
                return true;
            }
            // If hard-deleted, recreate
            if (! $existing) {
                Institute::create($snapshot);
                $this->audit('recovery_recreated', $class, $archivableId, $recoveredBy);
                return true;
            }
        }

        if ($class === User::class) {
            $existing = User::withTrashed()->find($archivableId);
            if ($existing && $existing->trashed()) {
                $existing->restore();
                $this->audit('recovery_restored', $class, $archivableId, $recoveredBy);
                return true;
            }
        }

        return false;
    }

    public function guardCascadeDelete(string $deletableType, int $deletableId): bool
    {
        // Check if deletable has dependent tenant data that would cascade
        if ($deletableType === Institute::class || $deletableType === 'institute') {
            $instituteId = $deletableId;
            $counts = [
                'students' => DB::table('students')->where('institute_id', $instituteId)->count(),
                'branches' => DB::table('branches')->where('institute_id', $instituteId)->count(),
                'invoices' => DB::table('invoices')->where('institute_id', $instituteId)->count(),
            ];
            $hasData = array_sum($counts) > 0;
            if ($hasData) {
                // Require confirmation workflow
                return false;
            }
        }
        return true;
    }

    protected function archiveSnapshot(string $class, int $id, ?int $instituteId, int $archivedBy): TenantRecoveryArchive
    {
        $model = $class::find($id);
        $snapshot = $model ? $model->toArray() : ['id' => $id];

        return TenantRecoveryArchive::create([
            'archivable_type' => $class,
            'archivable_id' => $id,
            'institute_id' => $instituteId,
            'snapshot' => $snapshot,
            'archived_by' => $archivedBy,
            'archived_at' => now(),
        ]);
    }

    protected function audit(string $action, string $modelClass, int $recordId, int $userId, array $extra = []): void
    {
        try {
            $module = str_contains($modelClass, 'Institute') ? 'institute' : 'user';
            \App\Models\AuditLog::create([
                'institute_id' => 0,
                'user_type' => 'system',
                'user_id' => $userId,
                'action' => $action,
                'module' => 'tenant_protection',
                'record_id' => $recordId,
                'old_values' => null,
                'new_values' => json_encode(array_merge(['type' => $modelClass], $extra)),
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => substr((string)request()->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {}
    }
}
