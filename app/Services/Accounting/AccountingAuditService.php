<?php

namespace App\Services\Accounting;

use App\Models\AccountingAuditTrail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Append-only audit logging for financial writes.
 *
 * Every mutation goes through this service so there is one place that owns the
 * before/after payloads, actor resolution and request metadata. Rows are never
 * updated or deleted.
 */
class AccountingAuditService
{
    /**
     * Record an audit event.
     *
     * @param  array<string, mixed>  $attrs  actor_type, actor_id, action,
     *                                       entity_type, entity_id, before_payload,
     *                                       after_payload, branch_id
     */
    public function log(int $instituteId, array $attrs): AccountingAuditTrail
    {
        return AccountingAuditTrail::create(array_merge([
            'institute_id' => $instituteId,
            'actor_type' => 'user',
            'actor_id' => null,
            'action' => 'create',
            'entity_type' => 'journal',
            'entity_id' => null,
            'before_payload' => null,
            'after_payload' => null,
            'branch_id' => null,
            'ip' => $this->requestIp(),
            'user_agent' => $this->requestUserAgent(),
            'created_at' => now(),
        ], $attrs));
    }

    /**
     * Read-only, tenant/branch-scoped listing for the audit-trail UI.
     *
     * @return LengthAwarePaginator<int, AccountingAuditTrail>
     */
    public function recent(int $instituteId, ?int $branchId, int $perPage = 50): LengthAwarePaginator
    {
        return AccountingAuditTrail::query()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($query) => $query->where(fn ($scope) => $scope
                ->where('branch_id', $branchId)
                ->orWhereNull('branch_id')))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    private function requestIp(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return app('request')->ip();
    }

    private function requestUserAgent(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }

        return app('request')->userAgent() ?: null;
    }
}
