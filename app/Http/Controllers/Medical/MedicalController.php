<?php

namespace App\Http\Controllers\Medical;

use App\Http\Controllers\Controller;
use App\Support\MedicalScope;

/**
 * Phase 1 plumbing base — NOT business logic.
 *
 * Resolves the current institute for every medical action and guards
 * route-model-bound records against cross-institute access. Form requests
 * already validate institute scoping; this is the controller-side backstop.
 */
abstract class MedicalController extends Controller
{
    protected function instituteId(): int
    {
        return MedicalScope::instituteIdOrFail();
    }

    /**
     * Abort 403 unless the record belongs to the current institute.
     */
    protected function ensureSameInstitute(object $record, string $what = 'record'): void
    {
        if (($record->institute_id ?? null) !== $this->instituteId()) {
            abort(403, "You do not have permission to access this {$what}.");
        }
    }
}
