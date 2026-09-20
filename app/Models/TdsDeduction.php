<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TdsDeduction extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'tds_deductions';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'rule_id',
        'country_code',
        'currency_code',
        'reference_no',
        'type',
        'payee_name',
        'payee_tin',
        'gross_amount',
        'tax_rate',
        'tax_amount',
        'deduction_date',
        'deposit_date',
        'deposit_challan_no',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'deduction_date' => 'date',
        'deposit_date' => 'date',
        'metadata' => 'array',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(TaxDeductionRule::class, 'rule_id');
    }

    public function certificates()
    {
        return $this->hasMany(TdsCertificate::class, 'deduction_id');
    }
}
