<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturnReconciliation extends Model
{
    use TenantScoped;

    protected $table = 'tax_return_reconciliations';

    protected $fillable = [
        'institute_id', 'country_code', 'financial_year',
        'tds_payable_total', 'tds_receivable_total', 'advance_tax_paid',
        'corporate_tax_payable', 'total_tax_liability', 'total_credits',
        'net_payable', 'currency_code', 'status', 'filing_date', 'acknowledgment_no',
        'breakdown', 'journal_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'tds_payable_total' => 'decimal:2',
        'tds_receivable_total' => 'decimal:2',
        'advance_tax_paid' => 'decimal:2',
        'corporate_tax_payable' => 'decimal:2',
        'total_tax_liability' => 'decimal:2',
        'total_credits' => 'decimal:2',
        'net_payable' => 'decimal:2',
        'breakdown' => 'array',
        'filing_date' => 'date',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
