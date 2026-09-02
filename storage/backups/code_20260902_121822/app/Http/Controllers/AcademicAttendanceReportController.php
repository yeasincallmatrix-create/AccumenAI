<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Institute;
use App\Models\Student;
use App\Services\AcademicAttendanceExportService;
use App\Services\AcademicAttendanceReportService;
use App\Support\CsvStream;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Step 20 — Academic attendance reports (student / class-group / daily).
 *
 * Every filter id is re-validated inside the tenant + branch scoped report
 * service (a bogus or out-of-scope id degrades to a clear message, never a
 * foreign write). The `{student}` route binding resolves through the scoped
 * Student model, so cross-tenant / cross-branch students yield a 404. Reports
 * are strictly read-only — nothing here writes to any table.
 *
 * Step 21 — each report also offers a CSV download of the exact same filtered
 * dataset (streamed, export buttons reuse the current query string).
 */
class AcademicAttendanceReportController extends Controller
{
    public function __construct(
        private readonly AcademicAttendanceReportService $reports,
        private readonly AcademicAttendanceExportService $exports,
    ) {}

    public function index(): View
    {
        return view('academic-attendance.reports.index');
    }

    public function student(Request $request, Student $student): View
    {
        $user = $request->user();

        $years = $this->reports->years();
        $selectedYearId = $request->filled('academic_year_id') ? (int) $request->input('academic_year_id') : null;
        $selectedYear = $years->firstWhere('id', $selectedYearId);

        [$start, $end] = $this->resolveWindow($request, $years, $selectedYear);

        $report = $this->reports->studentReport($student, $start, $end, $selectedYear);
        $records = $this->reports->studentRecords($student, $start, $end);

        return view('academic-attendance.reports.student', [
            'institute' => Institute::where('id', $user->institute_id)->first(),
            'student' => $student,
            'years' => $years,
            'selectedYearId' => $selectedYear?->id,
            'start' => $start,
            'end' => $end,
            'report' => $report,
            'records' => $records,
        ]);
    }

    public function classReport(Request $request): View
    {
        $user = $request->user();

        $yearId = $request->filled('academic_year_id') ? (int) $request->input('academic_year_id') : null;
        $classId = $request->filled('class_grade_id') ? (int) $request->input('class_grade_id') : null;
        $groupId = $request->filled('academic_group_id') ? (int) $request->input('academic_group_id') : null;

        $years = $this->reports->years();
        $selectedYear = $years->firstWhere('id', $yearId);

        [$start, $end] = $this->resolveWindow($request, $years, $selectedYear);

        $report = $this->reports->classReport($yearId, $classId, $groupId, $start, $end);

        return view('academic-attendance.reports.class', [
            'institute' => Institute::where('id', $user->institute_id)->first(),
            'years' => $years,
            'classes' => $this->reports->classOptions(),
            'groups' => $this->reports->groupOptions($yearId, $classId),
            'yearId' => $yearId,
            'classId' => $classId,
            'groupId' => $groupId,
            'start' => $start,
            'end' => $end,
            'report' => $report,
        ]);
    }

    public function daily(Request $request): View
    {
        $user = $request->user();

        $classId = $request->filled('class_grade_id') ? (int) $request->input('class_grade_id') : null;
        $groupId = $request->filled('academic_group_id') ? (int) $request->input('academic_group_id') : null;

        $date = $request->filled('attendance_date')
            ? $this->safeDate($request->input('attendance_date'), Carbon::today())
            : Carbon::today();

        $report = $this->reports->dailyReport($date, $classId, $groupId);

        return view('academic-attendance.reports.daily', [
            'institute' => Institute::where('id', $user->institute_id)->first(),
            'classes' => $this->reports->classOptions(),
            'groups' => $this->reports->groupOptions(null, $classId),
            'classId' => $classId,
            'groupId' => $groupId,
            'date' => $date,
            'report' => $report,
        ]);
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function resolveWindow(Request $request, Collection $years, ?AcademicYear $selectedYear): array
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
                // fall through to the default
            }
        }

        return Carbon::parse($default);
    }

    // ------------------------------------------------------------- Exports (Step 21)

    public function exportStudent(Request $request, Student $student)
    {
        $years = $this->reports->years();
        $selectedYearId = $request->filled('academic_year_id') ? (int) $request->input('academic_year_id') : null;
        $selectedYear = $years->firstWhere('id', $selectedYearId);

        [$start, $end] = $this->resolveWindow($request, $years, $selectedYear);

        return $this->csvStream($this->exports->student($student, $start, $end, $selectedYear));
    }

    public function exportClass(Request $request)
    {
        $yearId = $request->filled('academic_year_id') ? (int) $request->input('academic_year_id') : null;
        $classId = $request->filled('class_grade_id') ? (int) $request->input('class_grade_id') : null;
        $groupId = $request->filled('academic_group_id') ? (int) $request->input('academic_group_id') : null;

        $years = $this->reports->years();
        $selectedYear = $years->firstWhere('id', $yearId);

        [$start, $end] = $this->resolveWindow($request, $years, $selectedYear);

        return $this->csvStream($this->exports->classReport($yearId, $classId, $groupId, $start, $end));
    }

    public function exportDaily(Request $request)
    {
        $classId = $request->filled('class_grade_id') ? (int) $request->input('class_grade_id') : null;
        $groupId = $request->filled('academic_group_id') ? (int) $request->input('academic_group_id') : null;

        $date = $request->filled('attendance_date')
            ? $this->safeDate($request->input('attendance_date'), Carbon::today())
            : Carbon::today();

        return $this->csvStream($this->exports->daily($date, $classId, $groupId));
    }

    /**
     * @param  array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable}  $export
     */
    private function csvStream(array $export)
    {
        if (! $export['valid']) {
            abort(422, (string) $export['message']);
        }

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }
}
