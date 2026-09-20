<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CorporateTaxComputation extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'corporate_tax_computations';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'country_code',
        'currency_code',
        'reference_no',
        'financial_year',
        'entity_type',
        'total_income',
        'deductions',
        'taxable_income',
        'tax_rate',
        'tax_amount',
        'minimum_tax',
        'final_tax',
        'advance_tax_paid',
        'tax_payable',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'total_income' => 'decimal:2',
        'deductions' => 'decimal:2',
        'taxable_income' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'minimum_tax' => 'decimal:2',
        'final_tax' => 'decimal:2',
        'advance_tax_paid' => 'decimal:2',
        'tax_payable' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }
}
