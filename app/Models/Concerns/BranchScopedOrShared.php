<?php

namespace App\Models\Concerns;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Global scope for records that belong to a branch OR are shared across the
 * whole institute (branch_id NULL).
 *
 * While BranchContext is disabled the scope is a no-op, matching TenantScoped.
 * When enabled it keeps rows whose branch_id is NULL (institute-wide rows) as
 * well as rows belonging to the user's branch. Apply to accounting tables whose
 * branch_id is nullable; for tables with NOT NULL branch_id it behaves exactly
 * like the strict BranchScoped trait.
 */
trait BranchScopedOrShared
{
    public static function bootBranchScopedOrShared(): void
    {
        static::addGlobalScope('branch_shared', function (Builder $builder) {
            if (BranchContext::enabled()) {
                $builder->where(function (Builder $query) {
                    $query->whereNull('branch_id')->orWhere('branch_id', BranchContext::id());
                });
            }
        });

        static::creating(function ($model) {
            if (BranchContext::enabled() && array_key_exists('branch_id', $model->getAttributes()) && $model->branch_id !== null) {
                if ((int) $model->branch_id !== (int) BranchContext::id()) {
                    $model->branch_id = BranchContext::id();
                }
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('branch_id')) {
                $model->branch_id = $model->getOriginal('branch_id');
            }
            if ($model->isDirty('created_by')) {
                $model->created_by = $model->getOriginal('created_by');
            }
        });
    }
}
