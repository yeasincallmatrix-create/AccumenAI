<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reusable academic assessment type ("Mid Term", "Final", "First Term", ...).
 *
 * Global master: rows with institute_id NULL are system defaults shared by all
 * institutes; a row with an institute_id is that institute's own type/override.
 * Intentionally NOT TenantScoped (mirrors the global Subject master).
 */
class AssessmentType extends Model
{
    protected $table = 'assessment_types';

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
     * Global defaults plus the institute's own types.
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
