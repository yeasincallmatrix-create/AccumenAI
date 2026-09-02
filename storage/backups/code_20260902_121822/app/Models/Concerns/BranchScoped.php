<?php

namespace App\Models\Concerns;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Global scope that constrains a model to the branch the current user belongs
 * to. It mirrors TenantScoped: it is a no-op while BranchContext is disabled
 * or has no branch id (owners / institute admins / platform users see all
 * branches), so existing behaviour is preserved until a user is assigned a
 * branch.
 *
 * Apply only to tables that carry a direct `branch_id` (students, batches,
 * rooms, notices, transactions, users). Rows whose branch is inherited through
 * a relation (attendance, results, invoices, ...) are scoped by their owning
 * model instead.
 */
trait BranchScoped
{
    public static function bootBranchScoped(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (BranchContext::enabled()) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('branch_id'),
                    BranchContext::id()
                );
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
