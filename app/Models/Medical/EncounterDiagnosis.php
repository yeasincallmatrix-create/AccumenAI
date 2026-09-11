<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 15 — Structured encounter diagnosis (documentation only).
 *
 * A clinician-entered diagnosis attached to one encounter. This model is a
 * documentation record, NOT a terminology authority and NOT a diagnostic
 * engine: labels are preserved verbatim, codes are stored only when the
 * clinician supplies an authoritatively known one, and mapping_status stays
 * UNRESOLVED otherwise (never inferred, never fabricated).
 *
 * - diagnosis_type (primary|secondary|differential|symptom) is always
 *   explicitly chosen by the clinician; nothing auto-assigns primary.
 * - Lifecycle is active→removed; removal preserves the row (removed_at)
 *   for audit. There is no delete path.
 * - No patient_id column by design: the patient always resolves through
 *   the encounter, so cross-patient linkage is structurally impossible.
 */
class EncounterDiagnosis extends Model
{
    public const TYPE_PRIMARY = 'primary';

    public const TYPE_SECONDARY = 'secondary';

    public const TYPE_DIFFERENTIAL = 'differential';

    public const TYPE_SYMPTOM = 'symptom';

    public const TYPES = [
        self::TYPE_PRIMARY,
        self::TYPE_SECONDARY,
        self::TYPE_DIFFERENTIAL,
        self::TYPE_SYMPTOM,
    ];

    public const SOURCE_STRUCTURED = 'structured';

    public const SOURCE_FREE_TEXT = 'free_text';

    public const MAPPING_RESOLVED = 'resolved';

    public const MAPPING_UNRESOLVED = 'unresolved';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REMOVED = 'removed';

    /**
     * Code systems backed by a recognized authority. Intentionally EMPTY:
     * the repository has no ICD/SNOMED source, so nothing may resolve yet.
     * Future phases register systems here ONLY with authoritative backing;
     * until then every code stays UNRESOLVED and the raw label rules.
     */
    public const RECOGNIZED_CODE_SYSTEMS = [];

    public static function resolvesCode(?string $system): bool
    {
        return $system !== null
            && in_array(strtoupper(trim($system)), static::RECOGNIZED_CODE_SYSTEMS, true);
    }

    protected $fillable = [
        'institute_id',
        'encounter_id',
        'recorded_by',
        'label',
        'diagnosis_type',
        'source',
        'mapping_status',
        'code',
        'code_system',
        'notes',
        'status',
        'removed_at',
    ];

    protected $casts = [
        'removed_at' => 'datetime',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Audited soft-removal. Throws if already removed so double removal
     * (e.g. concurrent requests) fails loudly instead of double-auditing.
     */
    public function markRemoved(): void
    {
        if (! $this->isActive()) {
            throw new \RuntimeException('Diagnosis is already removed.');
        }

        $this->update([
            'status' => self::STATUS_REMOVED,
            'removed_at' => now(),
        ]);
    }
}
