<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Education fee head catalog (Step 37). A fee head names one billable fee
 * (admission, course/tuition, registration, exam, certificate, other), carries
 * its default amount and the income CoA it should credit, and can be
 * institute-wide (branch_id NULL) or branch-scoped.
 */
class FeeHead extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;

    protected $table = 'fee_heads';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'branch_id',
        'name',
        'code',
        'type',
        'default_amount',
        'income_coa_id',
        'description',
        'is_active',
        'is_recurring',
        'billing_frequency',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'default_amount' => 'float',
            'is_active' => 'boolean',
            'is_recurring' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public const TYPE_ADMISSION = 'admission';

    public const TYPE_COURSE_TUITION = 'course_tuition';

    public const TYPE_REGISTRATION = 'registration';

    public const TYPE_EXAM = 'exam';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_ADMISSION,
        self::TYPE_COURSE_TUITION,
        self::TYPE_REGISTRATION,
        self::TYPE_EXAM,
        self::TYPE_CERTIFICATE,
        self::TYPE_OTHER,
    ];

    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_QUARTERLY = 'quarterly';
    public const FREQ_ANNUALLY = 'annually';
    public const FREQ_ONE_TIME = 'one_time';

    public const BILLING_FREQUENCIES = [
        self::FREQ_MONTHLY,
        self::FREQ_QUARTERLY,
        self::FREQ_ANNUALLY,
        self::FREQ_ONE_TIME,
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('is_recurring', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'income_coa_id');
    }

    public function structureItems(): HasMany
    {
        return $this->hasMany(FeeStructureItem::class);
    }

    /**
     * Map the education fee head type to the legacy invoices.invoice_type enum
     * used by the finance core (no enum change needed).
     */
    public function invoiceType(): string
    {
        return match ($this->type) {
            self::TYPE_ADMISSION => 'admission',
            self::TYPE_COURSE_TUITION => 'course_fee',
            self::TYPE_EXAM => 'exam_fee',
            self::TYPE_CERTIFICATE => 'certificate_fee',
            default => 'other',
        };
    }

    public function displayName(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->type));
    }

    public function billingFrequencyLabel(): string
    {
        return match ($this->billing_frequency) {
            self::FREQ_MONTHLY => 'Monthly',
            self::FREQ_QUARTERLY => 'Quarterly',
            self::FREQ_ANNUALLY => 'Annually',
            self::FREQ_ONE_TIME => 'One-time',
            default => ucfirst($this->billing_frequency ?? 'one_time'),
        };
    }
}
