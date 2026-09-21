<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TdsCertificateReceived extends Model
{
    use TenantScoped;

    protected $table = 'tds_certificates_received';

    protected $fillable = [
        'institute_id', 'country_code', 'party_id', 'certificate_no', 'certificate_date',
        'tax_period', 'financial_year', 'total_base', 'total_tds', 'currency_code',
        'attachment_path', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'certificate_date' => 'date',
        'total_base' => 'decimal:2',
        'total_tds' => 'decimal:2',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(TdsReceivable::class, 'certificate_id');
    }
}
