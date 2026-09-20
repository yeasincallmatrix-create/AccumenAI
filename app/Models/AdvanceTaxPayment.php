<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdvanceTaxPayment extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'advance_tax_payments';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'country_code',
        'currency_code',
        'reference_no',
        'financial_year',
        'quarter',
        'estimated_income',
        'tax_rate',
        'tax_amount',
        'due_date',
        'payment_date',
        'challan_no',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'estimated_income' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'due_date' => 'date',
        'payment_date' => 'date',
        'metadata' => 'array',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
