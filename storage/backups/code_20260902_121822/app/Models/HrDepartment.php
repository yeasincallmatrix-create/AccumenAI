<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HR Department — industry-neutral organizational unit.
 *
 * Scope: per institute, optionally per branch. Hierarchical via parent_department_id.
 * Ordering via display_order. Active flag for soft-enabled/disable. Soft-deletable to preserve history.
 *
 * Tenant isolation: TenantScoped (institute_id).
 * Branch isolation: branch_id nullable; branch managers are constrained via BranchScopedOrShared when needed,
 *                   but the model uses BranchScoped via trait where appropriate — institute-wide rows (branch_id = NULL) remain visible unless filtering explicitly.
 *                   For HR-1 we expose both TenantScoped and a branch-aware scope helper; global scope is TenantScoped only.
 *                   Branch filtering is enforced at controller/service level using BranchContext.
 */
class HrDepartment extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'hr_departments';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_department_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_department_id')->orderBy('display_order')->orderBy('name');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrEmployee::class, 'department_id');
    }

    public function designations(): HasMany
    {
        return $this->hasMany(HrDesignation::class, 'department_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}
