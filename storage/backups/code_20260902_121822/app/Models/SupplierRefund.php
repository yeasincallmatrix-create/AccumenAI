<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRefund extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;
    protected $table = 'supplier_refunds';
    protected $guarded = [];
    protected $casts = ['amount'=>'decimal:4'];
    public function supplier(): BelongsTo { return $this->belongsTo(Party::class, 'supplier_id'); }
    public function purchaseReturn(): BelongsTo { return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class, 'journal_id'); }
}
