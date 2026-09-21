<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementLine extends Model
{
    use TenantScoped;

    protected $table = 'bank_statement_lines';

    protected $guarded = [];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:4',
        'match_confidence' => 'integer',
        'matched_at' => 'datetime',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'statement_id');
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(BankRule::class, 'rule_id');
    }

    public function matchedJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'matched_je_id');
    }

    public function categorizedAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'categorized_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class, 'statement_line_id');
    }
}
