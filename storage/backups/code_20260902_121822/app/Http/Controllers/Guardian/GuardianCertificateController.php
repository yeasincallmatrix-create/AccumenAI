<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\GuardianAuditService;
use App\Services\GuardianService;
use Illuminate\Http\Request;

/**
 * Step 47 — Guardian certificates page (read-only). Shows the student's own
 * certificates; active ones carry their public verification link.
 */
class GuardianCertificateController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
        private readonly GuardianAuditService $audit,
    ) {}

    public function show(Request $request, int $student)
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $student = $this->guardians->requireStudent($guardian, $student);

        $certificates = $student->certificates()
            ->with(['course', 'batch'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        $this->audit->record((int) $student->institute_id, (int) $guardian->getKey(), 'guardian_viewed_certificates', (int) $student->id);

        return view('guardian.certificates', [
            'guardian' => $guardian,
            'student' => $student,
            'certificates' => $certificates,
        ]);
    }
}
