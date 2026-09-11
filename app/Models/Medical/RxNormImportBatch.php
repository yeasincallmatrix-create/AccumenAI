<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 12 — auditable RxNorm import run (release-aware; mirrors the DGDA
 * batch discipline deliberately — same ops shape, separate release
 * semantics, no shared tables).
 */
class RxNormImportBatch extends Model
{
    // Explicit: Laravel would otherwise pluralize RxNorm → rx_norm_import_batches.
    protected $table = 'rxnorm_import_batches';
    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_COMPLETE = 'complete';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'source', 'release_version', 'filename', 'checksum', 'status',
        'records_seen', 'records_valid', 'records_created', 'records_updated',
        'records_unchanged', 'records_rejected', 'records_unmapped',
        'records_ambiguous', 'error_summary', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function concepts()
    {
        return $this->hasMany(RxNormConcept::class, 'rxnorm_import_batch_id');
    }
}
