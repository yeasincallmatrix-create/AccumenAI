<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single grade band inside a grade scale.
 *
 * The closed range [min_score, max_score] is INCLUSIVE on both ends and must
 * not overlap any other band of the same scale (AcademicGradingService
 * validates this) so a raw score resolves deterministically to exactly one
 * band — e.g. 80.00 always falls in the band whose range covers 80.
 *
 *   grade_point   numeric value used by GPA (or pass/fail-only subjects where
 *                 gpa_included = false)
 *   is_pass       PASS / FAIL subject verdict derived from the band
 *   gpa_included  whether this band may enter a GPA (false for e.g. a Fail or
 *                 award-only / non-credit band)
 */
class GradeScaleRow extends Model
{
    protected $table = 'grade_scale_rows';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_score' => 'float',
            'max_score' => 'float',
            'grade_point' => 'float',
            'is_pass' => 'boolean',
            'gpa_included' => 'boolean',
            'display_order' => 'integer',
            'status' => 'boolean',
        ];
    }

    public function scale(): BelongsTo
    {
        return $this->belongsTo(GradeScale::class, 'grade_scale_id');
    }
}
