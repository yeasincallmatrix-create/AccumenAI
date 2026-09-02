<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Follow-up task, optionally attached to a CRM subject
 * (contact|organization|lead) via the minimal polymorphic subject_type +
 * subject_id pair (both NULL for a standalone task).
 *
 * Tenant-scoped; branch rule mirrors the owning records (branch_id NULL =
 * whole-institute). Soft-deleted.
 */
class CrmTask extends Model
{
    use Concerns\BranchScopedOrShared;
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $table = 'crm_tasks';

    protected $guarded = [];

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
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
