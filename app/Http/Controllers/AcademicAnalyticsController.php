<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\AcademicGroup;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\User;
use App\Services\EducationAnalyticsExportService;
use App\Services\EducationAnalyticsService;
use App\Support\CsvStream;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Step 44 — Education Analytics & Reports.
 *
 * Administrator-focused analytics layer. Every action is a read-only render
 * (or a streamed CSV of the exact same filtered dataset) over the aggregates
 * computed in EducationAnalyticsService. The finance and CRM sections are
 * gated by the same permissions that gate those modules (finance.view /
 * crm.view) — a teacher holding only education.manage never sees finance or
 * CRM figures. Tenant + branch isolation is inherited from the scoped models.
 */
class AcademicAnalyticsController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly EducationAnalyticsService $analytics,
        private readonly EducationAnalyticsExportService $exports,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $filters = $this->filters($request);
        $branchId = filled($filters['branch_id'] ?? null) ? (int) $filters['branch_id'] : null;

        $overview = $this->analytics->overview(
            $this->can($request, 'finance.view'),
            $this->can($request, 'crm.view'),
            $branchId,
        );

        // Chart-ready aggregates for the overview page (single query reuse, no new tables)
        $attendanceReport = $this->analytics->attendance($filters);
        $resultReport = $this->analytics->results($filters);

        return view('academic.analytics.index', [
            'institute' => $institute,
            'overview' => $overview,
            'filters' => $filters,
            'options' => $this->options($request),
            'attendanceReport' => $attendanceReport,
            'resultReport' => $resultReport,
            'subIndustry' => $institute->sub_industry ?? '',
            'industryLabel' => $overview['academic']['industryLabel'] ?? 'Education',
            'subIndustryLabel' => $overview['academic']['subIndustryLabel'] ?? '',
        ]);
    }

    // ------------------------------------------------------------- Students

    public function students(Request $request): View
    {
        $filters = $this->filters($request);

        return view('academic.analytics.students', [
            'institute' => $this->requireInstitute($request),
            'data' => $this->analytics->students($filters),
            'options' => $this->options($request),
            'filters' => $filters,
        ]);
    }

    public function studentsExport(Request $request)
    {
        $export = $this->exports->students($this->filters($request));

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // -------------------------------------------------------------- Courses

    public function courses(Request $request): View
    {
        $filters = $this->filters($request);

        return view('academic.analytics.courses', [
            'institute' => $this->requireInstitute($request),
            'rows' => $this->analytics->courses($filters),
            'options' => $this->options($request),
            'filters' => $filters,
        ]);
    }

    public function coursesExport(Request $request)
    {
        $export = $this->exports->courses($this->filters($request));

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // -------------------------------------------------------------- Batches

    public function batches(Request $request): View
    {
        $filters = $this->filters($request);

        return view('academic.analytics.batches', [
            'institute' => $this->requireInstitute($request),
            'rows' => $this->analytics->batches($filters),
            'options' => $this->options($request),
            'filters' => $filters,
        ]);
    }

    public function batchesExport(Request $request)
    {
        $export = $this->exports->batches($this->filters($request));

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // ----------------------------------------------------------- Attendance

    public function attendance(Request $request): View
    {
        $filters = $this->filters($request);

        return view('academic.analytics.attendance', [
            'institute' => $this->requireInstitute($request),
            'report' => $this->analytics->attendance($filters),
            'options' => $this->options($request),
            'filters' => $filters,
        ]);
    }

    public function attendanceExport(Request $request)
    {
        $export = $this->exports->attendance($this->filters($request));

        if (! $export['valid']) {
            abort(422, $export['message']);
        }

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // -------------------------------------------------------------- Results

    public function results(Request $request): View
    {
        $filters = $this->filters($request);

        return view('academic.analytics.results', [
            'institute' => $this->requireInstitute($request),
            'report' => $this->analytics->results($filters),
            'options' => $this->options($request),
            'filters' => $filters,
        ]);
    }

    public function resultsExport(Request $request)
    {
        $export = $this->exports->results($this->filters($request));

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // ----------------------------------------------------------- Promotions

    public function promotions(Request $request): View
    {
        $filters = $this->filters($request);

        return view('academic.analytics.promotions', [
            'institute' => $this->requireInstitute($request),
            'rows' => $this->analytics->promotions($filters),
            'options' => $this->options($request),
            'filters' => $filters,
        ]);
    }

    public function promotionsExport(Request $request)
    {
        $export = $this->exports->promotions($this->filters($request));

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // ------------------------------------------------------------ Completion

    public function completion(Request $request): View
    {
        $filters = $this->filters($request);

        return view('academic.analytics.completion', [
            'institute' => $this->requireInstitute($request),
            'rows' => $this->analytics->completion($filters),
            'options' => $this->options($request),
            'filters' => $filters,
        ]);
    }

    public function completionExport(Request $request)
    {
        $export = $this->exports->completion($this->filters($request));

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // ---------------------------------------------------------- Certificates

    public function certificates(Request $request): View
    {
        $filters = $this->filters($request);

        return view('academic.analytics.certificates', [
            'institute' => $this->requireInstitute($request),
            'report' => $this->analytics->certificates($filters),
            'options' => $this->options($request),
            'filters' => $filters,
        ]);
    }

    public function certificatesExport(Request $request)
    {
        $export = $this->exports->certificates($this->filters($request));

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // -------------------------------------------------------------- Finance

    public function finance(Request $request): View
    {
        abort_unless($this->can($request, 'finance.view'), 403, 'You are not authorized to view finance analytics.');
        $branchId = filled($request->input('branch_id')) ? (int) $request->input('branch_id') : null;

        return view('academic.analytics.finance', [
            'institute' => $this->requireInstitute($request),
            'report' => $this->analytics->finance($branchId),
            'options' => $this->options($request),
            'filters' => $this->filters($request),
        ]);
    }

    public function financeExport(Request $request)
    {
        abort_unless($this->can($request, 'finance.view'), 403, 'You are not authorized to view finance analytics.');

        $export = $this->exports->finance();

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // ------------------------------------------------------------------ CRM

    public function crm(Request $request): View
    {
        abort_unless($this->can($request, 'crm.view'), 403, 'You are not authorized to view CRM analytics.');

        return view('academic.analytics.crm', [
            'institute' => $this->requireInstitute($request),
            'report' => $this->analytics->crm(),
            'options' => $this->options($request),
        ]);
    }

    public function crmExport(Request $request)
    {
        abort_unless($this->can($request, 'crm.view'), 403, 'You are not authorized to view CRM analytics.');

        $export = $this->exports->crm();

        return CsvStream::download($export['filename'], $export['headers'], $export['rows']);
    }

    // ------------------------------------------------------------- Helpers

    private function filters(Request $request): array
    {
        return [
            'term' => $request->input('term'),
            'status' => $request->input('status'),
            'branch_id' => $request->input('branch_id'),
            'course_id' => $request->input('course_id'),
            'batch_id' => $request->input('batch_id'),
            'class_grade_id' => $request->input('class_grade_id'),
            'academic_group_id' => $request->input('academic_group_id'),
            'academic_year_id' => $request->input('academic_year_id'),
            'admission_from' => $request->input('admission_from'),
            'admission_to' => $request->input('admission_to'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];
    }

    private function options(Request $request): array
    {
        $this->requireInstitute($request);

        $classIds = StudentAcademicPlacement::query()
            ->inScope()
            ->whereNotNull('class_grade_id')
            ->distinct()
            ->pluck('class_grade_id');

        $groupIds = StudentAcademicPlacement::query()
            ->inScope()
            ->whereNotNull('academic_group_id')
            ->distinct()
            ->pluck('academic_group_id');

        return [
            'years' => $this->analytics->years(),
            'branches' => Branch::query()->orderBy('name')->get(),
            'courses' => InstituteCourse::query()->with('course')->get()
                ->map(fn (InstituteCourse $ic) => $ic->course)
                ->filter()
                ->unique('id')
                ->values(),
            'batches' => Batch::query()->orderBy('name')->get(),
            'classes' => ClassGrade::query()->whereKey($classIds)->orderBy('name')->get(),
            'groups' => AcademicGroup::query()->whereKey($groupIds)->orderBy('name')->get(),
            'statuses' => Student::STATUSES,
        ];
    }

    private function can(Request $request, string $permission): bool
    {
        $user = $request->user();

        if ($user instanceof InstituteUser) {
            return $user->hasPermission($permission);
        }

        if ($user instanceof User) {
            return (bool) (Workspace::membership()?->hasPermission($permission));
        }

        return false;
    }
}
