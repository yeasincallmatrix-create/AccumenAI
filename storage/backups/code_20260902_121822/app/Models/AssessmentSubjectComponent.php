<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Component configuration for one subject inside one academic assessment:
 * the authoritative full mark / pass mark (and order/status). mandatory_pass is
 * reserved for the later pass/fail calculation layer.
 */
class AssessmentSubjectComponent extends Model
{
    protected $table = 'assessment_subject_components';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'full_mark' => 'float',
            'pass_mark' => 'float',
            'mandatory_pass' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function assessmentSubject(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubject::class, 'assessment_subject_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }
}
