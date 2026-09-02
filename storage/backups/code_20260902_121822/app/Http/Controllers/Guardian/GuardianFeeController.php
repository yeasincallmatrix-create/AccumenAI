<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\Education\StudentFinanceService;
use App\Services\GuardianAuditService;
use App\Services\GuardianService;
use Illuminate\Http\Request;

/**
 * Step 47 — Guardian fees page (read-only). Reuses the Step-37 education
 * finance ledger; never writes invoices or payments.
 */
class GuardianFeeController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
        private readonly StudentFinanceService $finance,
        private readonly GuardianAuditService $audit,
    ) {}

    public function show(Request $request, int $student)
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $student = $this->guardians->requireStudent($guardian, $student);

        $ledger = $this->finance->ledgerForStudent((int) $student->institute_id, (int) $student->id);

        $this->audit->record((int) $student->institute_id, (int) $guardian->getKey(), 'guardian_viewed_fees', (int) $student->id);

        return view('guardian.fees', [
            'guardian' => $guardian,
            'student' => $student,
            'ledger' => $ledger,
        ]);
    }
}
