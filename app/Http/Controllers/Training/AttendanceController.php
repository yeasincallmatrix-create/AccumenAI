<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingAttendance;
use App\Models\Training\TrainingEnrollment;
use App\Models\Training\TrainingSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $instituteId = (int) $request->user()->institute_id;
        $batches = TrainingBatch::query()
            ->where('institute_id', $instituteId)
            ->whereIn('status', ['upcoming', 'ongoing', 'completed'])
            ->with(['course:id,name'])
            ->withCount('enrollments')
            ->orderBy('name')
            ->get();

        // Batch selection
        $selectedBatchId = $request->query('batch_id') ? (int) $request->query('batch_id') : ($batches->first()?->id);
        $selectedBatch = $selectedBatchId ? $batches->firstWhere('id', $selectedBatchId) : null;

        // View mode: month (default) or week
        $viewMode = $request->query('view_mode', 'month');
        if (!in_array($viewMode, ['month', 'week'], true)) $viewMode = 'month';
        $weekDate = $request->query('week_date', now()->toDateString());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekDate)) $weekDate = now()->toDateString();

        // Month handling: expected format Y-m (e.g., 2026-08)
        $monthParam = $request->query('month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $monthParam = now()->format('Y-m');
        }
        [$year, $monthNum] = array_map('intval', explode('-', $monthParam));
        $year = max(2000, min(2100, $year));
        $monthNum = max(1, min(12, $monthNum));
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNum, $year);
        $month = sprintf('%04d-%02d', $year, $monthNum);

        // Build days array for view (unified for both modes)
        $days = [];
        $dayLabels = [];
        $monthYear = '';
        if ($viewMode === 'week') {
            try {
                $carbonWeek = Carbon::parse($weekDate);
            } catch (\Exception $e) {
                $carbonWeek = Carbon::now();
                $weekDate = $carbonWeek->toDateString();
            }
            $startOfWeek = $carbonWeek->copy()->startOfWeek(Carbon::MONDAY);
            $endOfWeek = $carbonWeek->copy()->endOfWeek(Carbon::SUNDAY);
            for ($d = $startOfWeek->copy(); $d->lte($endOfWeek); $d->addDay()) {
                $days[] = $d->copy();
                $dayLabels[] = $d->format('D d');
            }
            $monthYear = $startOfWeek->format('M Y');
            // For week view, override year/month to week start for fallback vars
            $year = (int) $startOfWeek->year;
            $monthNum = (int) $startOfWeek->month;
        } else {
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $c = Carbon::createFromDate($year, $monthNum, $d);
                $days[] = $c;
                $dayLabels[] = (string) $d;
            }
            $monthYear = Carbon::createFromDate($year, $monthNum, 1)->format('M Y');
        }

        // Fetch trainees for selected batch — unified source: enrollments table
        $trainees = collect();
        if ($selectedBatchId) {
            $trainees = TrainingEnrollment::where('batch_id', $selectedBatchId)
                ->where('institute_id', $instituteId)
                ->with('student:id,first_name,last_name,email,student_id,student_id_number,reg_no,user_id,full_name,name')
                ->get()
                ->map(function ($e) {
                    $s = $e->student ?? $e->trainee;
                    if (!$s) return null;
                    return (object)[
                        'id' => $s->id,
                        'name' => $s->full_name ?? $s->name ?? trim(($s->first_name ?? '').' '.($s->last_name ?? '')) ?: 'Unknown',
                        'first_name' => $s->first_name ?? '',
                        'last_name' => $s->last_name ?? '',
                        'email' => $s->email ?? '',
                        'student_id' => $s->student_id ?? null,
                        'student_id_number' => $s->student_id_number ?? null,
                        'reg_no' => $s->reg_no ?? null,
                        'user_id' => $s->user_id ?? null,
                    ];
                })->filter()->values();
        }

        // Fetch existing attendance for this batch/month (or week) to pre-check checkboxes
        $attendanceMap = [];
        if ($selectedBatchId && $trainees->isNotEmpty()) {
            if ($viewMode === 'week' && !empty($days)) {
                $startDate = $days[0]->toDateString();
                $endDate = end($days)->toDateString();
                // Reset pointer
                reset($days);
                $records = TrainingAttendance::where('batch_id', $selectedBatchId)
                    ->where('institute_id', $instituteId)
                    ->whereBetween('class_date', [$startDate, $endDate])
                    ->get(['student_id', 'class_date', 'status']);
            } else {
                $records = TrainingAttendance::where('batch_id', $selectedBatchId)
                    ->where('institute_id', $instituteId)
                    ->whereYear('class_date', $year)
                    ->whereMonth('class_date', $monthNum)
                    ->get(['student_id', 'class_date', 'status']);
            }

            foreach ($records as $rec) {
                $sid = $rec->student_id;
                $dateStr = $rec->class_date instanceof \DateTimeInterface ? $rec->class_date->format('Y-m-d') : (string) $rec->class_date;
                if (!isset($attendanceMap[$sid])) $attendanceMap[$sid] = [];
                $attendanceMap[$sid][$dateStr] = $rec->status;
            }
        }

        $batchStart = $selectedBatch?->start_date ? ( $selectedBatch->start_date instanceof \DateTimeInterface ? $selectedBatch->start_date->format('Y-m-d') : substr((string) $selectedBatch->start_date, 0, 10) ) : null;
        $batchEnd = $selectedBatch?->end_date ? ( $selectedBatch->end_date instanceof \DateTimeInterface ? $selectedBatch->end_date->format('Y-m-d') : substr((string) $selectedBatch->end_date, 0, 10) ) : null;

        // Weekly schedule → off days. Plan rows are effective-dated, so every date
        // is checked against the version of the weekly plan that applied on that
        // date: past months keep their plan, changes apply from the day they are made.
        // A batch with no schedule rows at all keeps every day tickable.
        $hasSchedule = $selectedBatchId ? TrainingSchedule::where('batch_id', $selectedBatchId)->exists() : false;
        $offDayMap = array_fill_keys(
            $this->offDatesForRange($selectedBatchId, array_map(fn ($d) => $d->toDateString(), $days)),
            true
        );
        $classDayCount = count($days) - count($offDayMap);

        return view('training.attendance.index', compact('batches', 'selectedBatchId', 'selectedBatch', 'trainees', 'daysInMonth', 'year', 'monthNum', 'month', 'attendanceMap', 'batchStart', 'batchEnd', 'viewMode', 'weekDate', 'days', 'dayLabels', 'monthYear', 'offDayMap', 'classDayCount', 'hasSchedule'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:training_batches,id',
            'date' => 'required|date',
            'attendance' => 'required|array',
        ]);
        $instituteId = (int) $request->user()->institute_id;
        return redirect()->route('training.attendance.index', ['batch_id' => $request->batch_id, 'date' => $request->date])
            ->with('status', 'Attendance saved for '.count($request->attendance).' trainees on '.$request->date);
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:training_batches,id',
            'month' => 'nullable|regex:/^\d{4}-\d{2}$/',
            'view_mode' => 'nullable|in:month,week',
            'week_date' => 'nullable|regex:/^\d{4}-\d{2}-\d{2}$/',
            'attendance' => 'nullable|array',
        ]);

        $instituteId = (int) $request->user()->institute_id;
        $batchId = (int) $request->batch_id;

        // Determine view mode and build date list
        $viewMode = $request->input('view_mode', 'month');
        if (!in_array($viewMode, ['month', 'week'], true)) $viewMode = 'month';
        $month = $request->input('month', now()->format('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = now()->format('Y-m');
        $weekDate = $request->input('week_date', now()->toDateString());
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekDate)) $weekDate = now()->toDateString();

        $dates = [];
        $daysInMonth = 0;
        $year = 0; $monthNum = 0;
        if ($viewMode === 'week') {
            try {
                $carbonWeek = Carbon::parse($weekDate);
            } catch (\Exception $e) {
                $carbonWeek = Carbon::now();
                $weekDate = $carbonWeek->toDateString();
            }
            $startOfWeek = $carbonWeek->copy()->startOfWeek(Carbon::MONDAY);
            $endOfWeek = $carbonWeek->copy()->endOfWeek(Carbon::SUNDAY);
            for ($d = $startOfWeek->copy(); $d->lte($endOfWeek); $d->addDay()) {
                $dates[] = $d->toDateString();
            }
        } else {
            [$year, $monthNum] = array_map('intval', explode('-', $month));
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $monthNum, $year);
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dates[] = sprintf('%04d-%02d-%02d', $year, $monthNum, $day);
            }
        }

        // Verify batch belongs to institute
        $batch = TrainingBatch::where('institute_id', $instituteId)->findOrFail($batchId);

        // Weekly schedule → off days are not class days: no rows are written for
        // them and any stale rows are removed so they never count in totals.
        // Each date uses the plan version that was in effect on that date.
        $offDates = $this->offDatesForRange($batchId, $dates);
        if ($offDates) {
            TrainingAttendance::where('institute_id', $instituteId)
                ->where('batch_id', $batchId)
                ->whereIn('class_date', $offDates)
                ->delete();
            $dates = array_values(array_diff($dates, $offDates));
        }

        // Get all trainees currently enrolled (to handle unchecked = absent)
        $enrolledIds = TrainingEnrollment::where('batch_id', $batchId)
            ->where('institute_id', $instituteId)
            ->pluck('student_id')
            ->toArray();
        if (empty($enrolledIds)) {
            return redirect()->back()->with('status', 'No trainees enrolled in this batch.');
        }

        $attendanceInput = $request->input('attendance', []); // [trainee_id => [day => 'present']]

        $membership = DB::table('institution_user')
            ->where('user_id', auth()->id())
            ->where('institution_id', $instituteId)
            ->where('status', 'active')
            ->first();

        $markedBy = null; // Default fallback — attendance.marked_by is nullable

        // If the user is authenticated via institute_user guard, use that ID directly
        if ($request->user() instanceof \App\Models\InstituteUser) {
            $markedBy = $request->user()->id;
        }
        // If we have a membership with a legacy link, use that
        elseif ($membership && $membership->legacy_institute_user_id) {
            $markedBy = $membership->legacy_institute_user_id;
        }
        // Otherwise, keep $markedBy as NULL (safe fallback — FK allows NULL)

        $batchStart = $batch->start_date ? ( $batch->start_date instanceof \DateTimeInterface ? $batch->start_date->format('Y-m-d') : substr((string) $batch->start_date, 0, 10) ) : null;
        $batchEnd = $batch->end_date ? ( $batch->end_date instanceof \DateTimeInterface ? $batch->end_date->format('Y-m-d') : substr((string) $batch->end_date, 0, 10) ) : null;
        $skipped = 0;

        foreach ($enrolledIds as $traineeId) {
            foreach ($dates as $date) {
                // Skip dates outside batch duration
                if ($batchStart && $date < $batchStart) { $skipped++; continue; }
                if ($batchEnd && $date > $batchEnd) { $skipped++; continue; }

                // Support both date-string keys (week view) and day-number keys (month view / legacy)
                $dayNum = (int) Carbon::parse($date)->format('j');
                $isPresent = false;
                if (isset($attendanceInput[$traineeId][$date]) && $attendanceInput[$traineeId][$date] === 'present') {
                    $isPresent = true;
                } elseif (isset($attendanceInput[$traineeId][$dayNum]) && $attendanceInput[$traineeId][$dayNum] === 'present') {
                    $isPresent = true;
                } elseif (isset($attendanceInput[$traineeId][(string)$dayNum]) && $attendanceInput[$traineeId][(string)$dayNum] === 'present') {
                    $isPresent = true;
                }
                $status = $isPresent ? 'present' : 'absent';

                TrainingAttendance::updateOrCreate(
                    [
                        'institute_id' => $instituteId,
                        'batch_id' => $batchId,
                        'student_id' => $traineeId,
                        'class_date' => $date,
                    ],
                    [
                        'status' => $status,
                        'marked_by' => $markedBy,
                    ]
                );
            }
        }

        if ($viewMode === 'week') {
            $startDate = $dates[0] ?? $weekDate;
            $endDate = end($dates) ?: $weekDate;
            $msg = 'Attendance saved for '.count($enrolledIds).' trainees for week '.$startDate.' to '.$endDate.' ('.count($dates).' class day(s)).';
            if ($offDates) {
                $msg .= ' '.count($offDates).' off day(s) excluded (no class scheduled).';
            }
            if ($skipped > 0) {
                $msg .= ' '.$skipped.' day(s) skipped (outside batch duration: '.($batchStart ?? '—').' to '.($batchEnd ?? '—').').';
            }
            return redirect()->route('training.attendance.index', ['batch_id' => $batchId, 'view_mode' => 'week', 'week_date' => $weekDate, 'month' => $month])
                ->with('status', $msg);
        }

        $msg = 'Attendance saved for '.count($enrolledIds).' trainees for '.$month.' ('.count($dates).' class day(s)).';
        if ($offDates) {
            $msg .= ' '.count($offDates).' off day(s) excluded (no class scheduled).';
        }
        if ($skipped > 0) {
            $msg .= ' '.$skipped.' day(s) skipped (outside batch duration: '.($batchStart ?? '—').' to '.($batchEnd ?? '—').').';
        }
        return redirect()->route('training.attendance.index', ['batch_id' => $batchId, 'month' => $month, 'view_mode' => 'month', 'week_date' => $weekDate])
            ->with('status', $msg);
    }

    /**
     * Dates (Y-m-d, ascending) in the range that are off days under the weekly
     * plan version in effect on each date. Empty array = no schedule configured
     * (every day counts as a class day).
     */
    private function offDatesForRange(?int $batchId, array $dates): array
    {
        if (!$batchId || $dates === []) {
            return [];
        }

        $hasSchedule = TrainingSchedule::where('batch_id', $batchId)->exists();
        if (!$hasSchedule) {
            return [];
        }

        $minDate = $dates[0];
        $maxDate = end($dates);
        $planRows = TrainingSchedule::where('batch_id', $batchId)
            ->activeBetween($minDate, $maxDate)
            ->get(['id', 'day_of_week', 'effective_from', 'effective_to']);

        $offDates = [];
        foreach ($dates as $date) {
            // Schedule day_of_week is 0=Mon..6=Sun, Carbon dayOfWeek is 0=Sun..6=Sat.
            $scheduleDay = ((int) Carbon::parse($date)->dayOfWeek + 6) % 7;
            $hasClass = $planRows->contains(
                fn ($row) => (int) $row->day_of_week === $scheduleDay && $row->isActiveOn($date)
            );
            if (!$hasClass) {
                $offDates[] = $date;
            }
        }

        return $offDates;
    }
}
