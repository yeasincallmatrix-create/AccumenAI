<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Services\AcademicAttendanceMarkingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Academic attendance marking workflow (Step 19).
 *
 * The roster is always rebuilt server-side from tenant + branch scoped
 * placements; the submitted student list is only intersected with that roster.
 * All writes stay in the legacy `attendance` table and are gated behind
 * permission:attendance.manage — the same capability that guards legacy
 * attendance management.
 */
class AcademicAttendanceController extends Controller
{
    public function __construct(
        private readonly AcademicAttendanceMarkingService $marking,
    ) {}

    /**
     * Marking page: filters (year / class / group / date), roster and the
     * saved summary for the selected date.
     */
    public function index(Request $request): View
    {
        $yearId = $request->filled('academic_year_id') ? (int) $request->input('academic_year_id') : null;
        $classId = $request->filled('class_grade_id') ? (int) $request->input('class_grade_id') : null;
        $groupId = $request->filled('academic_group_id') ? (int) $request->input('academic_group_id') : null;

        $date = $request->filled('attendance_date') ? Carbon::parse($request->input('attendance_date')) : Carbon::today();

        $context = $yearId !== null && $classId !== null
            ? $this->marking->rosterForContext($yearId, $classId, $groupId, $date)
            : [
                'valid' => false,
                'message' => null,
                'year' => null,
                'roster' => collect(),
                'summary' => null,
            ];

        return view('academic-attendance.index', [
            'years' => $this->marking->years(),
            'classes' => $this->marking->classOptions(),
            'groups' => $this->marking->groupOptions($yearId, $classId),
            'yearId' => $yearId,
            'classId' => $classId,
            'groupId' => $groupId,
            'date' => $date,
            'context' => $context,
        ]);
    }

    /**
     * Save (or update) the roster attendance for one date.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'academic_year_id' => [
                'required', 'integer',
                Rule::exists('academic_years', 'id')->where('institute_id', $user->institute_id),
            ],
            'class_grade_id' => ['required', 'integer', Rule::exists('class_grades', 'id')],
            'academic_group_id' => ['nullable', 'integer', Rule::exists('academic_groups', 'id')],
            'attendance_date' => ['required', 'date'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => ['nullable', 'string', Rule::in(Attendance::STATUSES)],
        ]);

        $submitted = [];
        foreach ((array) ($data['statuses'] ?? []) as $studentId => $status) {
            if (is_string($status) && $status !== '') {
                $submitted[(int) $studentId] = $status;
            }
        }

        $result = $this->marking->saveContext(
            (int) $data['academic_year_id'],
            (int) $data['class_grade_id'],
            $data['academic_group_id'] !== null ? (int) $data['academic_group_id'] : null,
            Carbon::parse($data['attendance_date']),
            $submitted,
            (int) $user->institute_id,
            (int) $user->id,
        );

        $summary = $result['summary'];
        $messages = [
            sprintf(
                'Attendance saved — %d present, %d absent, %d late, %d leave (%s).',
                $summary['present'],
                $summary['absent'],
                $summary['late'],
                $summary['leave'],
                $summary['present_percent'] !== null ? number_format($summary['present_percent'], 1).'%' : '—',
            ),
        ];
        foreach ($result['skipped'] as $studentId => $reason) {
            $messages[] = "Student #{$studentId} skipped ($reason).";
        }

        return back()->with('status', implode(' ', $messages));
    }
}
