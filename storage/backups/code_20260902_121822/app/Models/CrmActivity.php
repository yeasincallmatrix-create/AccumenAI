<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Timeline activity / follow-up / interaction attached to a CRM subject
 * (contact|organization|lead) via the minimal polymorphic subject_type +
 * subject_id pair. Types: call, email, meeting, follow_up, note, system.
 *
 * Tenant-scoped; branch rule mirrors the owning records (branch_id NULL =
 * whole-institute). Soft-deleted.
 */
class CrmActivity extends Model
{
    use Concerns\BranchScopedOrShared;
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $table = 'crm_activities';

    protected $guarded = [];

    public const TYPE_CALL = 'call';

    public const TYPE_EMAIL = 'email';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_FOLLOW_UP = 'follow_up';

    public const TYPE_NOTE = 'note';

    public const TYPE_SYSTEM = 'system';

    public const TYPES = [
        self::TYPE_CALL,
        self::TYPE_EMAIL,
        self::TYPE_MEETING,
        self::TYPE_FOLLOW_UP,
        self::TYPE_NOTE,
        self::TYPE_SYSTEM,
    ];

    protected function casts(): array
    {
        return [
            'activity_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'assigned_user_id');
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
