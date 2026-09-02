<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CRM organization (company / institution) record.
 *
 * Tenant-scoped. Branch rule mirrors the academic models: branch_id NULL =
 * whole-institute record visible to every branch; otherwise the record belongs
 * to one branch and is only visible to that branch's users (or institute-wide
 * users). Soft-deleted.
 */
class CrmOrganization extends Model
{
    use Concerns\BranchScopedOrShared;
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $table = 'crm_organizations';

    protected $guarded = [];

    public const CRM_SUBJECT_TYPE = 'organization';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'is_customer' => 'boolean',
            'is_prospect' => 'boolean',
            'customer_since' => 'date',
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'organization_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'organization_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CrmNote::class, 'subject_id')->where('subject_type', 'organization');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'subject_id')->where('subject_type', 'organization');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'subject_id')->where('subject_type', 'organization');
    }

    public function displayName(): string
    {
        return $this->name;
    }
}
