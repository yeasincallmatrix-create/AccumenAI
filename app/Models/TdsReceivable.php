<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TdsReceivable extends Model
{
    use TenantScoped;

    protected $table = 'tds_receivables';

    protected $fillable = [
        'institute_id', 'country_code', 'party_id', 'invoice_id', 'reference_no',
        'gross_amount', 'rate_percent', 'tds_amount', 'net_amount', 'currency_code',
        'deduction_date', 'tax_period', 'financial_year', 'status', 'certificate_id',
        'journal_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'rate_percent' => 'decimal:2',
        'tds_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'deduction_date' => 'date',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(TdsCertificateReceived::class, 'certificate_id');
    }

    public function scopePendingCert($q)
    {
        return $q->where('status', 'pending_certificate');
    }

    public function scopeCertified($q)
    {
        return $q->where('status', 'certified');
    }

    public function scopeForFy($q, string $fy)
    {
        return $q->where('financial_year', $fy);
    }
}
