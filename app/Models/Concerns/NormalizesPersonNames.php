<?php

namespace App\Models\Concerns;

use App\Support\NameCaser;

/**
 * Ensures first_name / last_name (and related person-name columns, when
 * present) are always stored in Initial Caps, even for console jobs,
 * seeders, imports and API paths that bypass HTTP middleware.
 */
trait NormalizesPersonNames
{
    protected static function bootNormalizesPersonNames(): void
    {
        static::saving(function ($model) {
            foreach (['first_name', 'last_name', 'middle_name', 'father_name', 'mother_name', 'guardian_name', 'emergency_contact_name'] as $col) {
                try {
                    if ($model->isDirty($col) && is_string($model->getAttribute($col))) {
                        $model->setAttribute($col, NameCaser::title($model->getAttribute($col)));
                    }
                } catch (\Throwable $e) {
                    // Never break a save on a cosmetic normalization.
                }
            }

            // Keep Student::full_name in sync when first/last change.
            try {
                if ($model->isDirty(['first_name', 'last_name']) && $model->isFillable('full_name')) {
                    $full = trim(($model->getAttribute('first_name') ?? '').' '.($model->getAttribute('last_name') ?? ''));
                    if ($full !== '') {
                        $model->setAttribute('full_name', $full);
                    }
                }
            } catch (\Throwable $e) {
            }
        });
    }
}
