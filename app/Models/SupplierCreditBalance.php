<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCreditBalance extends Model
{
    use BranchScopedOrShared;
    use TenantScoped;
    protected $table = 'supplier_credit_balances';
    protected $guarded = [];
    protected $casts = ['credit_amount'=>'decimal:4','used_amount'=>'decimal:4','remaining_amount'=>'decimal:4'];
    public function supplier(): BelongsTo { return $this->belongsTo(Party::class, 'supplier_id'); }
    public function purchaseReturn(): BelongsTo { return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id'); }
}
