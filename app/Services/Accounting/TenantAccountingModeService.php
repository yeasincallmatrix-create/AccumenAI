<?php

namespace App\Services\Accounting;

use App\Models\Institute;

class TenantAccountingModeService
{
    /**
     * Check if advanced accounting is enabled for a tenant.
     *
     * Schema-guarded: returns false (simple mode) when the column
     * hasn't migrated yet, so a missing migration can never 500 pages.
     */
    public function isAdvancedEnabled(?int $instituteId = null): bool
    {
        $id = $instituteId ?? tenant_id();

        if (! $id) {
            return false;
        }

        try {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('institutes', 'advanced_accounting_enabled')) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return (bool) Institute::where('id', $id)
            ->value('advanced_accounting_enabled');
    }

    /**
     * Enable advanced accounting for a tenant.
     */
    public function enable(int $instituteId): void
    {
        Institute::where('id', $instituteId)
            ->update(['advanced_accounting_enabled' => true]);
    }

    /**
     * Disable advanced accounting for a tenant.
     */
    public function disable(int $instituteId): void
    {
        Institute::where('id', $instituteId)
            ->update(['advanced_accounting_enabled' => false]);
    }

    /**
     * Toggle and return new state.
     */
    public function toggle(int $instituteId): bool
    {
        if ($this->isAdvancedEnabled($instituteId)) {
            $this->disable($instituteId);

            return false;
        }
        $this->enable($instituteId);

        return true;
    }
}
