<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single import run of a geography data package.
 *
 * Geo data is global shared reference data: an import row belongs to a country
 * in the shared `countries` table (never an institute/tenant). `created_by`
 * points at the platform admin (Super Admin) who triggered the import.
 */
class GeoImport extends Model
{
    protected $table = 'geo_imports';

    protected $guarded = [];

    protected $casts = [
        'file_size' => 'integer',
        'total_records' => 'integer',
        'inserted_records' => 'integer',
        'updated_records' => 'integer',
        'skipped_records' => 'integer',
        'duplicate_count' => 'integer',
        'error_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'created_by');
    }
}
