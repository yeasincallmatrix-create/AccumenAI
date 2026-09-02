<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Services\AcademicAttendanceReportService;
use App\Services\GuardianAuditService;
use App\Services\GuardianService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Step 47 — Guardian attendance page (read-only). Reuses the Step-20
 * attendance report service so unrecorded days are never treated as absences.
 */
class GuardianAttendanceController extends Controller
{
    public function __construct(
        private readonly GuardianService $guardians,
        private readonly AcademicAttendanceReportService $attendance,
        private readonly GuardianAuditService $audit,
    ) {}

    public function show(Request $request, int $student)
    {
        /** @var Guardian $guardian */
        $guardian = auth('guardian')->user();

        $student = $this->guardians->requireStudent($guardian, $student);

        $years = $this->attendance->years();

        $selectedYear = $request->filled('academic_year_id')
            ? $years->firstWhere('id', (int) $request->input('academic_year_id'))
            : null;

        [$start, $end] = $this->resolveWindow($request, $years, $selectedYear);

        $report = $this->attendance->studentReport($student, $start, $end, $selectedYear);
        $records = $this->attendance->studentRecords($student, $start, $end);

        $this->audit->record((int) $student->institute_id, (int) $guardian->getKey(), 'guardian_viewed_attendance', (int) $student->id);

        return view('guardian.attendance', [
            'guardian' => $guardian,
            'student' => $student,
            'years' => $years,
            'selectedYear' => $selectedYear,
            'start' => $start,
            'end' => $end,
            'report' => $report,
            'records' => $records,
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $years
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveWindow(Request $request, Collection $years, $selectedYear): array
    {
        $defaultStart = $selectedYear?->start_date
            ?? $years->pluck('start_date')->filter()->min()
            ?? Carbon::today()->startOfYear();

        $defaultEnd = $selectedYear?->end_date
            ?? $years->pluck('end_date')->filter()->max()
            ?? Carbon::today();

        $start = $this->safeDate($request->input('start_date'), $defaultStart);
        $end = $this->safeDate($request->input('end_date'), $defaultEnd);

        if ($start->gt($end)) {
            $start = Carbon::parse($defaultStart);
            $end = Carbon::parse($defaultEnd);
        }

        return [$start, $end];
    }

    private function safeDate(mixed $value, mixed $default): Carbon
    {
        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                // fall through to default
            }
        }

        return Carbon::parse($default);
    }
}
