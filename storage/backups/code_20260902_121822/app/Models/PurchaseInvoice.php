<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use BranchScopedOrShared;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'purchase_invoices';
    protected $guarded = [];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'grand_total' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'due_amount' => 'decimal:4',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_POSTED,
        self::STATUS_CANCELLED,
        self::STATUS_REVERSED,
    ];

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Party::class, 'supplier_id'); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id'); }
    public function goodsReceipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id'); }
    public function currency(): BelongsTo { return $this->belongsTo(Currency::class); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class, 'journal_id'); }
    public function items(): HasMany { return $this->hasMany(PurchaseInvoiceItem::class, 'purchase_invoice_id')->orderBy('sort_order'); }
    public function payments(): HasMany { return $this->hasMany(PurchaseSupplierPayment::class, 'purchase_invoice_id'); }

    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isPosted(): bool { return $this->status === self::STATUS_POSTED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
    public function isReversed(): bool { return $this->status === self::STATUS_REVERSED; }
    public function canEdit(): bool { return $this->status === self::STATUS_DRAFT; }
    public function canPost(): bool { return $this->status === self::STATUS_DRAFT; }
    public function canCancel(): bool { return $this->status === self::STATUS_DRAFT; }
    public function canReverse(): bool { return $this->status === self::STATUS_POSTED && $this->journal_id !== null; }
}
