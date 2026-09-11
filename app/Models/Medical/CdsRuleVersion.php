<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 13 — one immutable material version of a rule. Rows are never
 * edited after findings reference them; corrections create new versions.
 */
class CdsRuleVersion extends Model
{
    protected $fillable = [
        'cds_rule_id', 'version', 'definition', 'status',
        'effective_from', 'effective_until', 'validated_by', 'validated_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'definition' => 'array',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(CdsRule::class, 'cds_rule_id');
    }

    public function findings()
    {
        return $this->hasMany(CdsFinding::class, 'cds_rule_version_id');
    }

    public function isEffectiveNow(): bool
    {
        if ($this->status !== CdsRule::STATUS_ACTIVE) {
            return false;
        }
        $now = now();
        if ($this->effective_from && $now->lt($this->effective_from)) {
            return false;
        }
        if ($this->effective_until && $now->gt($this->effective_until)) {
            return false;
        }

        return true;
    }
}
