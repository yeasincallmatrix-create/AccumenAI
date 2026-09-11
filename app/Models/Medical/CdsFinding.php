<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 13 — one evaluated CDS finding. Tenant-scoped clinical record:
 * institute + patient relational (never inside JSON), prescription link
 * nullable (draft removal keeps history), rule-version link immortal.
 */
class CdsFinding extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_OVERRIDDEN = 'overridden';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'institute_id', 'patient_id', 'prescription_id', 'cds_rule_version_id',
        'severity', 'status', 'message', 'explanation', 'trigger_data',
        'evaluated_at', 'resolved_by', 'resolved_at', 'resolution_reason',
    ];

    protected $casts = [
        'explanation' => 'array',
        'trigger_data' => 'array',
        'evaluated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function ruleVersion()
    {
        return $this->belongsTo(CdsRuleVersion::class, 'cds_rule_version_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
