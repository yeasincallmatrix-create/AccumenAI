<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Global scope that constrains a model to the current institute.
 *
 * Applied only to tables that belong exclusively to one institute
 * (students, batches, results, ...). Course/Subject catalogs are
 * multi-tenant and deliberately do NOT use this trait.
 */
trait TenantScoped
{
    public static function bootTenantScoped(): void
    {
        static::addGlobalScope('institute', function (Builder $builder) {
            if (TenantContext::enabled()) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('institute_id'),
                    TenantContext::id()
                );
            }
        });

        // Mass-assignment / IDOR hardening: institute ownership is always from TenantContext
        static::creating(function ($model) {
            if (TenantContext::enabled() && $model->isFillable('institute_id') === false && array_key_exists('institute_id', $model->getAttributes())) {
                // guarded=[] allows mass assignment — force to context to prevent override
                if ((int) $model->institute_id !== (int) TenantContext::id()) {
                    $model->institute_id = TenantContext::id();
                }
            } elseif (TenantContext::enabled() && ! isset($model->institute_id)) {
                $model->institute_id = TenantContext::id();
            } elseif (TenantContext::enabled() && isset($model->institute_id) && (int) $model->institute_id !== (int) TenantContext::id()) {
                $model->institute_id = TenantContext::id();
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('institute_id')) {
                $model->institute_id = $model->getOriginal('institute_id');
            }
            // prevent audit field tampering via mass assignment
            if ($model->isDirty('created_by')) {
                $model->created_by = $model->getOriginal('created_by');
            }
        });
    }
}
