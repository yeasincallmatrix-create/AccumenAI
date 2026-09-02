<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Free-text note attached to a CRM subject (contact|organization|lead) via the
 * minimal polymorphic subject_type + subject_id pair.
 *
 * Tenant-scoped; branch rule mirrors the owning records (branch_id NULL =
 * whole-institute). Soft-deleted.
 */
class CrmNote extends Model
{
    use Concerns\BranchScopedOrShared;
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $table = 'crm_notes';

    protected $guarded = [];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    /**
     * Resolve the owning CRM subject. Not an Eloquent morph relation; validated
     * by the service layer.
     */
    public function subject(): ?Model
    {
        return match ($this->subject_type) {
            'contact' => CrmContact::query()->find($this->subject_id),
            'organization' => CrmOrganization::query()->find($this->subject_id),
            'lead' => CrmLead::query()->find($this->subject_id),
            default => null,
        };
    }
}
