<?php

namespace App\Models;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Step 46 — Generic document (industry-neutral).
 *
 * A single document row links to any entity via a polymorphic relationship.
 * The physical file lives on the configured disk (public by default) at a
 * relative path; the row carries the full metadata: original name, MIME type,
 * extension, size, checksum and an incrementing version. Tenant-scoped; the
 * branch rule mirrors CRM (branch_id NULL = whole-institute, visible to every
 * branch). Soft-deleted.
 */
class Document extends Model
{
    use Concerns\DeletesFiles;
    use Concerns\TenantScoped;
    use SoftDeletes;

    protected $fileColumns = ['file_path'];

    protected $table = 'documents';

    protected $guarded = [];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const VERIFICATION_PENDING = 'pending_verification';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_REJECTED = 'rejected';

    public const VERIFICATION_EXPIRED = 'expired';

    public const VERIFICATION_REPLACED = 'replaced';

    public const VERIFICATION_STATUSES = [
        self::VERIFICATION_PENDING,
        self::VERIFICATION_VERIFIED,
        self::VERIFICATION_REJECTED,
        self::VERIFICATION_EXPIRED,
        self::VERIFICATION_REPLACED,
    ];

    public const SOURCE_UPLOADED = 'uploaded';

    public const SOURCE_GENERATED = 'generated';

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'version' => 'integer',
            'verified_at' => 'datetime',
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (BranchContext::enabled()) {
                $builder->where(function (Builder $query) {
                    $query->whereNull('branch_id')->orWhere('branch_id', BranchContext::id());
                });
            }
        });
    }

    /**
     * Route-model binding resolves soft-deleted documents too, so restore and
     * force-delete endpoints can operate on the same {document} parameter.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->withTrashed()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'verified_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderBy('version');
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(StudentAcademicPlacement::class, 'placement_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Training\Enrollment::class, 'enrollment_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFICATION_VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->verification_status === self::VERIFICATION_REJECTED;
    }

    public function isExpired(): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }

        return ! $this->expiry_date->isPast()
            && $this->expiry_date->lte(now()->addDays($days));
    }

    /**
     * Effective verification status: an expired date overrides a verified
     * status without mutating the stored row.
     */
    public function effectiveVerificationStatus(): string
    {
        $status = $this->verification_status ?? self::VERIFICATION_PENDING;

        if ($this->isExpired() && $status === self::VERIFICATION_VERIFIED) {
            return self::VERIFICATION_EXPIRED;
        }

        return $status;
    }
}
