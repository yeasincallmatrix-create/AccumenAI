<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reusable component master ("Written", "MCQ", "Practical", "Viva", ...).
 *
 * The same component can be reused across any number of subjects and
 * assessments; the per-assessment-subject full/pass marks live in
 * assessment_subject_components. Global rows have institute_id NULL.
 */
class Component extends Model
{
    protected $table = 'components';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Global defaults plus the institute's own components.
     */
    public function scopeAvailableFor(Builder $query, Institute $institute): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('institute_id')->orWhere('institute_id', $institute->id))
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('name');
    }
}
