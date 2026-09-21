<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankRule extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'bank_rules';
    protected $fillable = [
        'institute_id', 'branch_id', 'name', 'priority',
        'match_operator', 'pattern_field', 'pattern_type', 'pattern_value',
        'amount_operator', 'amount_min', 'amount_max', 'direction',
        'action_type', 'account_id', 'party_id', 'narration',
        'is_active', 'times_applied',
    ];

    protected $casts = [
        'priority' => 'integer',
        'amount_min' => 'decimal:4',
        'amount_max' => 'decimal:4',
        'is_active' => 'boolean',
        'times_applied' => 'integer',
    ];

    public const PATTERN_TYPES = ['contains', 'starts_with', 'ends_with', 'exact', 'regex'];
    public const PATTERN_FIELDS = ['description', 'reference', 'counterparty'];
    public const ACTION_TYPES = ['categorize', 'suggest_je', 'set_party', 'ignore'];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Institute::class);
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function party(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeForInstitute($q, $id)
    {
        return $q->where('institute_id', $id);
    }

    public function matches(BankStatementLine $line): bool
    {
        $fieldValue = match ($this->pattern_field) {
            'description' => $line->description ?? '',
            'reference' => $line->reference ?? '',
            'counterparty' => $line->counterparty ?? '',
            default => '',
        };

        $matched = match ($this->pattern_type) {
            'contains' => stripos($fieldValue, $this->pattern_value) !== false,
            'starts_with' => stripos($fieldValue, $this->pattern_value) === 0,
            'ends_with' => substr_compare(
                strtolower($fieldValue),
                strtolower($this->pattern_value),
                -strlen($this->pattern_value)
            ) === 0,
            'exact' => strcasecmp($fieldValue, $this->pattern_value) === 0,
            'regex' => @preg_match('/' . $this->pattern_value . '/i', $fieldValue) === 1,
            default => false,
        };

        if (!$matched) return false;

        if ($this->amount_operator && $this->amount_min !== null) {
            $amt = (float) abs($line->amount);
            $min = (float) $this->amount_min;
            $max = $this->amount_max !== null ? (float) $this->amount_max : null;

            $amountOk = match ($this->amount_operator) {
                '=' => abs($amt - $min) < 0.01,
                '>' => $amt > $min,
                '<' => $amt < $min,
                '>=' => $amt >= $min,
                '<=' => $amt <= $min,
                'between' => $max !== null && $amt >= $min && $amt <= $max,
                default => true,
            };
            if (!$amountOk) return false;
        }

        if ($this->direction && $this->direction !== 'any') {
            $lineDirection = $line->type;
            if ($this->direction !== $lineDirection) return false;
        }

        return true;
    }
}
