<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnRefund extends Model
{
    protected $table = 'sales_return_refunds';
    protected $guarded = [];
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'refund_date' => 'date',
        ];
    }
    public function return(): BelongsTo { return $this->belongsTo(SalesReturn::class, 'return_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class, 'journal_id'); }
}
