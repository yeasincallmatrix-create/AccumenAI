<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseReturn extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'purchase_returns';
    protected $guarded = [];
    protected $casts = [
        'return_date' => 'date',
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = ['draft','submitted','approved','posted','cancelled','reversed'];

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Party::class, 'supplier_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function goodsReceipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id'); }
    public function purchaseInvoice(): BelongsTo { return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(InventoryWarehouse::class, 'warehouse_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class, 'journal_id'); }
    public function items(): HasMany { return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id')->orderBy('sort_order'); }

    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isSubmitted(): bool { return $this->status === self::STATUS_SUBMITTED; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isPosted(): bool { return $this->status === self::STATUS_POSTED; }
    public function canSubmit(): bool { return $this->status === self::STATUS_DRAFT; }
    public function canApprove(): bool { return $this->status === self::STATUS_SUBMITTED; }
    public function canPost(): bool { return $this->status === self::STATUS_APPROVED; }
    public function canCancel(): bool { return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_APPROVED], true); }
    public function canReverse(): bool { return $this->status === self::STATUS_POSTED && $this->journal_id !== null; }
}
