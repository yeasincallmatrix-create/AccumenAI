<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CRM lead (potential customer / prospect) record.
 *
 * Tenant-scoped. Branch rule mirrors the academic models: branch_id NULL =
 * whole-institute record visible to every branch; otherwise the record belongs
 * to one branch and is only visible to that branch's users (or institute-wide
 * users). Soft-deleted.
 */
class CrmLead extends Model
{
    use Concerns\BranchScopedOrShared;
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $table = 'crm_leads';

    protected $guarded = [];

    public const CRM_SUBJECT_TYPE = 'lead';

    protected function casts(): array
    {
        return [
            'value_amount' => 'decimal:2',
            'converted_at' => 'datetime',
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

    public function status(): BelongsTo
    {
        return $this->belongsTo(CrmLeadStatus::class, 'status_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmLeadSource::class, 'source_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(CrmOrganization::class, 'organization_id');
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

    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'converted_contact_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CrmNote::class, 'subject_id')->where('subject_type', 'lead');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'subject_id')->where('subject_type', 'lead');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'subject_id')->where('subject_type', 'lead');
    }

    public function displayName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
