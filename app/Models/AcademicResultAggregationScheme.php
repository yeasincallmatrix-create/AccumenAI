<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aggregation scheme for one academic context (year + class + group).
 *
 * Declares WHICH assessments combine into an aggregated result and (through
 * academic_result_aggregation_items) each assessment's manually configured
 * weight. It is pure configuration: it never touches assessment instances or
 * marks rows, and the same assessment can participate in many schemes.
 *
 * Institute + branch scoping mirrors AcademicAssessment: branch_id NULL =
 * whole-institute scheme (every branch can see it); otherwise the scheme
 * belongs to one branch and is only visible to that branch's users (or
 * institute-wide users).
 */
class AcademicResultAggregationScheme extends Model
{
    use TenantScoped;

    protected $table = 'academic_result_aggregation_schemes';

    public $timestamps = true;

    protected $guarded = [];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
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

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classGrade(): BelongsTo
    {
        return $this->belongsTo(ClassGrade::class);
    }

    public function academicGroup(): BelongsTo
    {
        return $this->belongsTo(AcademicGroup::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AcademicResultAggregationItem::class, 'scheme_id')->orderBy('display_order')->orderBy('id');
    }

    /**
     * Total configured weight of active items (the manual configuration, never
     * changed by calculation). With DECIMAL(5,2) storage, exact 2-decimal
     * total is expected; no tolerance hack needed.
     */
    public function totalWeight(): float
    {
        return round((float) $this->items()->where('status', 'active')->sum('weight'), 2);
    }

    public function weightIsValid(): bool
    {
        // P2-3 — Exact comparison after DECIMAL(5,2) migration (no float tolerance).
        return $this->totalWeight() === 100.0;
    }
}
