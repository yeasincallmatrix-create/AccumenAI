<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CRM contact (person) record.
 *
 * Tenant-scoped. Branch rule mirrors the academic models: branch_id NULL =
 * whole-institute record visible to every branch; otherwise the record belongs
 * to one branch and is only visible to that branch's users (or institute-wide
 * users). Soft-deleted.
 */
class CrmContact extends Model
{
    use Concerns\BranchScopedOrShared;
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $table = 'crm_contacts';

    protected $guarded = [];

    public const CRM_SUBJECT_TYPE = 'contact';

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

    public function contactType(): BelongsTo
    {
        return $this->belongsTo(CrmContactType::class, 'contact_type_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(CrmOrganization::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmLeadSource::class, 'source_id');
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

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'contact_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CrmNote::class, 'subject_id')->where('subject_type', 'contact');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'subject_id')->where('subject_type', 'contact');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'subject_id')->where('subject_type', 'contact');
    }

    public function displayName(): string
    {
        return trim(($this->salutation ? $this->salutation.' ' : '').$this->first_name.' '.$this->last_name);
    }
}
