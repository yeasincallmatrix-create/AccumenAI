<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankReconciliation extends Model
{
    use TenantScoped;

    protected $table = 'bank_reconciliations';

    protected $guarded = [];

    protected $casts = [
        'matched_at' => 'datetime',
    ];

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'statement_line_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'matched_by');
    }
}
