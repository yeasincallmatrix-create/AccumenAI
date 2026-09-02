<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\AcademicFinalResult;
use App\Models\Guardian;
use App\Services\AcademicAttendanceReportService;
use App\Services\Education\StudentFinanceService;
use App\Services\GuardianService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Step 47 — Guardian dashboard: an overview of the currently selected
 * linked student. Strictly read-only.
 */
class GuardianDashboardController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
        private readonly StudentFinanceService $finance,
        private readonly AcademicAttendanceReportService $attendance,
    ) {}

    public function show(Request $request)
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $student = $this->guardians->activeStudent($guardian);

        $data = [
            'guardian' => $guardian,
            'student' => $student,
            'enrollment' => $student !== null ? $this->guardians->currentEnrollment($student) : null,
            'attendance' => null,
            'fees' => null,
            'result_count' => 0,
            'certificate_count' => 0,
        ];

        if ($student === null) {
            return view('guardian.dashboard', $data);
        }

        $enrollment = $data['enrollment'];
        $year = $enrollment?->batch?->academicYear;

        $start = $year?->start_date ?? $student->admission_date ?? Carbon::today()->startOfYear();
        $end = $year?->end_date ?? Carbon::today();

        $data['attendance'] = $this->attendance->studentReport(
            $student,
            Carbon::parse($start)->startOfDay(),
            Carbon::parse($end)->endOfDay(),
        )['totals'] ?? null;

        $data['fees'] = $this->finance->ledgerForStudent((int) $student->institute_id, (int) $student->id)['totals'] ?? null;

        $placementIds = $student->academicPlacements()->pluck('id');

        $data['result_count'] = AcademicFinalResult::query()
            ->where('status', AcademicFinalResult::STATUS_PUBLISHED)
            ->whereHas('students', fn ($q) => $q->whereIn('placement_id', $placementIds))
            ->count();

        $data['certificate_count'] = $student->certificates()
            ->whereIn('status', ['active', 'pending'])
            ->count();

        return view('guardian.dashboard', $data);
    }
}
