<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 12 — one RxNorm source concept (stable RXCUI identity).
 *
 * Source-faithful row: TTY semantics, release provenance and match state.
 * Links to normalized terminology only on deterministic grounds; ambiguous
 * rows keep candidate sets as data. Retirements mark status, never delete.
 */
class RxNormConcept extends Model
{
    // Explicit: Laravel would otherwise pluralize RxNorm → rx_norm_concepts.
    protected $table = 'rxnorm_concepts';
    public const MATCH_UNMAPPED = 'unmapped';

    public const MATCH_EXACT = 'exact';

    public const MATCH_DETERMINISTIC = 'deterministic';

    public const MATCH_RELATIONSHIP = 'relationship';

    public const MATCH_AMBIGUOUS = 'ambiguous';

    public const MATCH_INVALID = 'invalid';

    /** Term types the importer understands; anything else is rejected. */
    public const KNOWN_TTYS = [
        'IN', 'PIN', 'MIN', // ingredient-level
        'BN', // brand name
        'SCD', 'SBD', 'SCDC', 'SBDC', // clinical/branded drug (+components)
        'GPCK', 'BPCK', // packs
        'DF', // dose form
    ];

    /** TTYs that attach at ingredient level (never products). */
    public const INGREDIENT_TTYS = ['IN', 'PIN', 'MIN'];

    /** TTYs that attach at product level (formulation agreement required). */
    public const PRODUCT_TTYS = ['SCD', 'SBD', 'SCDC', 'SBDC'];

    protected $fillable = [
        'rxnorm_import_batch_id', 'rxcui', 'name', 'tty', 'source_release',
        'status', 'replaced_by_rxcui', 'medicine_product_id',
        'medicine_ingredient_id', 'match_status', 'match_detail', 'candidates',
        'raw_payload', 'retrieved_at',
    ];

    protected $casts = [
        'candidates' => 'array',
        'raw_payload' => 'array',
        'retrieved_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(RxNormImportBatch::class, 'rxnorm_import_batch_id');
    }

    public function product()
    {
        return $this->belongsTo(MedicineProduct::class, 'medicine_product_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(MedicineIngredient::class, 'medicine_ingredient_id');
    }
}
