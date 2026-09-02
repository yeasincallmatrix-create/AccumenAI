<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Read model for a student's certificate history on the Academic History page.
 *
 * It surfaces the same set of records the platform registry considers an
 * official certificate — status `active` (issued) and `revoked` — and
 * deliberately excludes `pending` / `rejected` requests, which are
 * work-in-progress rather than history. Certificate numbering itself is
 * shared with the approval flow through Certificate::numberFor(), so the
 * number shown here is always the one persisted on approval.
 */
class StudentAcademicCertificateService
{
    public const OFFICIAL_STATUSES = ['active', 'revoked'];

    /**
     * Official certificates for the student, newest issued first, with the
     * relations the page renders (course, batch, institute).
     *
     * Tenant + branch isolation is inherited from the scoped models: the
     * student is resolved through the branch scope upstream, and every
     * certificate is constrained to the current institute by the Certificate
     * model's global scope.
     */
    public function forStudent(Student $student): Collection
    {
        return $student->certificates()
            ->with(['course', 'batch', 'institute', 'type'])
            ->whereIn('status', self::OFFICIAL_STATUSES)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }
}
