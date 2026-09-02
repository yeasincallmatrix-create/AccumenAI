<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One assessment participating in an aggregation scheme with a manually
 * configured weight. The weight is a scheme-level configuration value and is
 * preserved verbatim — the calculation layer only ever reads it.
 */
class AcademicResultAggregationItem extends Model
{
    protected $table = 'academic_result_aggregation_items';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'display_order' => 'integer',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(AcademicResultAggregationScheme::class, 'scheme_id');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(AcademicAssessment::class, 'academic_assessment_id');
    }
}
