<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TdsCertificate extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'tds_certificates';

    public $timestamps = true;

    protected $fillable = [
        'institute_id',
        'deduction_id',
        'country_code',
        'certificate_no',
        'financial_year',
        'issue_date',
        'status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'metadata' => 'array',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function deduction(): BelongsTo
    {
        return $this->belongsTo(TdsDeduction::class, 'deduction_id');
    }
}
