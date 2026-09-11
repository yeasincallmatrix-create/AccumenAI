<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 11 — auditable DGDA import run. Every synchronization writes its
 * outcome here first; partial/failed runs are explicit states, never
 * silent. Registrations point at their batch for provenance.
 */
class DgdaImportBatch extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_COMPLETE = 'complete';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'source', 'filename', 'checksum', 'status',
        'records_seen', 'records_valid', 'records_created', 'records_updated',
        'records_unchanged', 'records_rejected', 'records_unmatched',
        'records_ambiguous', 'error_summary', 'source_version',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function registrations()
    {
        return $this->hasMany(DgdaRegistration::class, 'dgda_import_batch_id');
    }
}
