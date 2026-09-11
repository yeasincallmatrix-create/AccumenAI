<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 11 — one DGDA marketing authorization (DAR number).
 *
 * Regulatory record, NOT clinical identity: brand/manufacturer/validity as
 * issued, source spelling preserved. Links to a global product only when
 * the match is exact (same DAR#) or fully deterministic; otherwise the row
 * stays unmatched/ambiguous with its raw payload intact for review.
 */
class DgdaRegistration extends Model
{
    public const MATCH_UNMATCHED = 'unmatched';

    public const MATCH_EXACT = 'exact_identifier';

    public const MATCH_DETERMINISTIC = 'deterministic';

    public const MATCH_AMBIGUOUS = 'ambiguous';

    public const MATCH_INVALID = 'invalid';

    protected $fillable = [
        'dgda_import_batch_id', 'dar_number', 'brand_name', 'generic_name',
        'strength_raw', 'dosage_form_raw', 'manufacturer_name', 'importer_name',
        'status_raw', 'valid_upto', 'medicine_product_id', 'match_status',
        'match_detail', 'raw_payload', 'retrieved_at',
    ];

    protected $casts = [
        'valid_upto' => 'date',
        'raw_payload' => 'array',
        'retrieved_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(DgdaImportBatch::class, 'dgda_import_batch_id');
    }

    public function product()
    {
        return $this->belongsTo(MedicineProduct::class, 'medicine_product_id');
    }
}
