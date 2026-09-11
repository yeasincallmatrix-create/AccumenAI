<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 13 — CDS rule identity. Global vocabulary (no patient data, no
 * tenant columns). Only rows that are validated + active (+ within any
 * effective window) participate in evaluation; everything else is inert.
 */
class CdsRule extends Model
{
    public const TYPE_ALLERGY = 'ALLERGY';

    public const TYPE_INTERACTION = 'INTERACTION';

    public const TYPE_DUPLICATE_THERAPY = 'DUPLICATE_THERAPY';

    public const TYPE_CONTRAINDICATION = 'CONTRAINDICATION';

    public const TYPE_DOSE_CHECK = 'DOSE_CHECK';

    public const TYPE_DURATION_CHECK = 'DURATION_CHECK';

    public const TYPE_PATIENT_FACTOR = 'PATIENT_FACTOR';

    public const TYPE_TERMINOLOGY = 'TERMINOLOGY';

    public const TYPE_OTHER = 'OTHER';

    public const SEVERITY_INFO = 'INFO';

    public const SEVERITY_LOW = 'LOW';

    public const SEVERITY_MODERATE = 'MODERATE';

    public const SEVERITY_HIGH = 'HIGH';

    public const SEVERITY_CRITICAL = 'CRITICAL';

    public const POLICY_BLOCK = 'block';

    public const POLICY_WARN = 'warn';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'rule_key', 'rule_type', 'severity', 'block_policy', 'status',
        'source', 'source_version', 'validated_by', 'validated_at',
        'current_version', 'effective_from', 'effective_until',
    ];

    protected $casts = [
        'current_version' => 'integer',
        'validated_at' => 'datetime',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
    ];

    public function versions()
    {
        return $this->hasMany(CdsRuleVersion::class, 'cds_rule_id');
    }

    public function activeVersion()
    {
        return $this->hasOne(CdsRuleVersion::class, 'cds_rule_id')
            ->where('status', self::STATUS_ACTIVE)
            ->orderByDesc('version');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isEffectiveNow(): bool
    {
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
