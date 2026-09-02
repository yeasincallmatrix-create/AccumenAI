<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseSupplierPayment extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;

    protected $table = 'purchase_supplier_payments';
    protected $guarded = [];
    protected $casts = [
        'amount' => 'decimal:4',
        'paid_at' => 'datetime',
    ];

    public function institute(): BelongsTo { return $this->belongsTo(Institute::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function purchaseInvoice(): BelongsTo { return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id'); }
    public function supplier(): BelongsTo { return $this->belongsTo(Party::class, 'supplier_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class, 'journal_id'); }
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class, 'payment_method_id'); }
}
