<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 04 — Database-backed counter for clinical numbering.
 *
 * One row per (institute, sequence_type, year). Allocation happens under a
 * row lock inside NumberSequenceService; this model carries no logic beyond
 * the relations so the locking discipline stays in exactly one place.
 */
class NumberSequence extends Model
{
    public const TYPE_MR = 'mr';

    public const TYPE_PRESCRIPTION = 'prescription';

    public const TYPE_LAB_ORDER = 'lab_order';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_TPA_CLAIM = 'tpa_claim';

    public const TYPE_ENCOUNTER = 'encounter';

    protected $fillable = [
        'institute_id',
        'sequence_type',
        'year',
        'last_number',
    ];

    protected $casts = [
        'year' => 'integer',
        'last_number' => 'integer',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }
}
