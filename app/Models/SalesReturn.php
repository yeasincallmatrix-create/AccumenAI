<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'sales_returns';

    protected $guarded = [];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = ['draft','approved','posted','cancelled','reversed'];

    public const REFUND_NONE = 'none';
    public const REFUND_PENDING = 'pending';
    public const REFUND_PARTIAL = 'partial';
    public const REFUND_REFUNDED = 'refunded';
    public const REFUND_CREDITED = 'credited';

    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_APPROVED, self::STATUS_POSTED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_POSTED, self::STATUS_CANCELLED],
        self::STATUS_POSTED => [self::STATUS_REVERSED],
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'refundable_amount' => 'decimal:4',
            'refunded_amount' => 'decimal:4',
            'meta' => 'array',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class, 'invoice_id'); }
    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'order_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Party::class, 'customer_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id'); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class, 'currency_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class, 'journal_id'); }
    public function inventoryJournal(): BelongsTo { return $this->belongsTo(Journal::class, 'inventory_journal_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(InstituteUser::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(SalesReturnItem::class, 'return_id'); }
    public function refunds(): HasMany { return $this->hasMany(SalesReturnRefund::class, 'return_id'); }
    public function reversal(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of'); }

    public function canApprove(): bool { return $this->status === self::STATUS_DRAFT; }
    public function canPost(): bool { return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED], true); }
    public function canCancel(): bool { return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED], true); }
    public function canReverse(): bool { return $this->status === self::STATUS_POSTED; }
    public function isPosted(): bool { return $this->status === self::STATUS_POSTED; }
    public function isImmutable(): bool { return in_array($this->status, [self::STATUS_POSTED, self::STATUS_REVERSED, self::STATUS_CANCELLED], true); }
    public function canEdit(): bool { return $this->status === self::STATUS_DRAFT; }
    public function canTransitionTo(string $target): bool { return in_array($target, self::TRANSITIONS[$this->status] ?? [], true); }
}
