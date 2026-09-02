<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\CalendarEvent;
use App\Models\Course;
use App\Models\InstituteUser;
use App\Models\Room;
use App\Models\Subject;
use App\Services\CalendarEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __construct(
        private readonly CalendarEventService $service,
    ) {}

    public function index(Request $request): View
    {
        $instituteId = $request->user()->institute_id;
        $view = $request->query('view', 'month');
        $date = $request->query('date', now()->format('Y-m-d'));

        $startDate = match ($view) {
            'day' => $date,
            'week' => now()->parse($date)->startOfWeek()->toDateString(),
            'month' => now()->parse($date)->startOfMonth()->toDateString(),
            default => now()->parse($date)->startOfMonth()->toDateString(),
        };

        $endDate = match ($view) {
            'day' => $date,
            'week' => now()->parse($date)->endOfWeek()->toDateString(),
            'month' => now()->parse($date)->endOfMonth()->toDateString(),
            default => now()->parse($date)->endOfMonth()->toDateString(),
        };

        $events = $this->service->queryRange(
            $instituteId,
            $startDate,
            $endDate,
            $request->query('branch_id') ? (int) $request->query('branch_id') : null,
            $request->query('teacher_id') ? (int) $request->query('teacher_id') : null,
            $request->query('batch_id') ? (int) $request->query('batch_id') : null,
            $request->query('room_id') ? (int) $request->query('room_id') : null,
            $request->query('course_id') ? (int) $request->query('course_id') : null,
            $request->query('subject_id') ? (int) $request->query('subject_id') : null,
            $request->query('event_type'),
            $request->query('academic_year_id') ? (int) $request->query('academic_year_id') : null,
        );

        return view('calendar.index', [
            'events' => $events,
            'view' => $view,
            'date' => $date,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'batches' => Batch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'courses' => Course::where('institute_id', $instituteId)->orderBy('name')->get(),
            'teachers' => InstituteUser::where('institute_id', $instituteId)->orderBy('first_name')->get(),
            'rooms' => Room::where('institute_id', $instituteId)->orderBy('name')->get(),
            'subjects' => Subject::where('institute_id', $instituteId)->orderBy('name')->get(),
            'academicYears' => AcademicYear::where('institute_id', $instituteId)->orderByDesc('start_date')->get(),
            'eventTypes' => CalendarEventService::EVENT_TYPES,
            'filters' => $request->only(['branch_id', 'teacher_id', 'batch_id', 'room_id', 'course_id', 'subject_id', 'event_type', 'academic_year_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'event_type' => ['required', 'string', 'in:'.implode(',', CalendarEventService::EVENT_TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'is_all_day' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'batch_id' => ['nullable', 'integer', 'exists:batches,id'],
            'class_grade_id' => ['nullable', 'integer'],
            'academic_group_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer', 'exists:institute_users,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'academic_year_id' => ['nullable', 'integer'],
            'recurrence_rule' => ['nullable', 'array'],
            'recurrence_rule.frequency' => ['required_with:recurrence_rule', 'string', 'in:daily,weekly,monthly'],
            'recurrence_rule.interval' => ['nullable', 'integer', 'min:1'],
            'recurrence_rule.days_of_week' => ['nullable', 'array'],
            'recurrence_rule.days_of_week.*' => ['string', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'recurrence_rule.end_date' => ['nullable', 'date'],
            'recurrence_rule.max_occurrences' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $instituteId = $request->user()->institute_id;
        $branchId = $data['branch_id'] ?? $request->user()->branch_id;
        $isAllDay = $data['is_all_day'] ?? false;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;

        $conflicts = $this->service->detectConflicts(
            $instituteId,
            $data['start_date'],
            $startTime,
            $endTime,
            $isAllDay,
            $data['teacher_id'] ?? null,
            $data['room_id'] ?? null,
            $data['batch_id'] ?? null,
        );

        if ($this->service->hasConflicts($conflicts)) {
            $messages = [];
            foreach ($conflicts as $type => $items) {
                if ($items->isNotEmpty()) {
                    $names = $items->pluck('title')->implode(', ');
                    $messages[] = ucfirst($type)." conflict: {$names}";
                }
            }

            return back()->withInput()->with('error', 'Scheduling conflict detected — '.implode('; ', $messages));
        }

        $data['institute_id'] = $instituteId;
        $data['branch_id'] = $branchId;
        $data['created_by'] = $request->user()->id;
        $data['is_all_day'] = $isAllDay;
        $data['end_date'] = $data['end_date'] ?? $data['start_date'];

        $this->service->create($data);

        return redirect()->route('calendar.index')->with('status', 'Event created.');
    }

    public function show(CalendarEvent $calendarEvent): View
    {
        $calendarEvent->load(['course', 'batch', 'teacher', 'room', 'subject', 'branch', 'academicYear', 'classGrade', 'reminders']);

        return view('calendar.show', ['event' => $calendarEvent]);
    }

    public function update(Request $request, CalendarEvent $calendarEvent): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'event_type' => ['sometimes', 'required', 'string', 'in:'.implode(',', CalendarEventService::EVENT_TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['sometimes', 'required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'is_all_day' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'batch_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
            'status' => ['sometimes', 'string', 'in:active,cancelled'],
            'recurrence_rule' => ['nullable', 'array'],
        ]);

        $startDate = $data['start_date'] ?? $calendarEvent->start_date->format('Y-m-d');
        $startTime = $data['start_time'] ?? $calendarEvent->start_time;
        $endTime = $data['end_time'] ?? $calendarEvent->end_time;
        $isAllDay = $data['is_all_day'] ?? $calendarEvent->is_all_day;
        $teacherId = $data['teacher_id'] ?? $calendarEvent->teacher_id;
        $roomId = $data['room_id'] ?? $calendarEvent->room_id;
        $batchId = $data['batch_id'] ?? $calendarEvent->batch_id;

        $conflicts = $this->service->detectConflicts(
            $calendarEvent->institute_id,
            $startDate,
            $startTime,
            $endTime,
            $isAllDay,
            $teacherId,
            $roomId,
            $batchId,
            $calendarEvent->id,
        );

        if ($this->service->hasConflicts($conflicts)) {
            $messages = [];
            foreach ($conflicts as $type => $items) {
                if ($items->isNotEmpty()) {
                    $names = $items->pluck('title')->implode(', ');
                    $messages[] = ucfirst($type)." conflict: {$names}";
                }
            }

            return back()->with('error', 'Scheduling conflict — '.implode('; ', $messages));
        }

        $this->service->update($calendarEvent, $data);

        return redirect()->route('calendar.index')->with('status', 'Event updated.');
    }

    public function destroy(CalendarEvent $calendarEvent): RedirectResponse
    {
        $this->service->destroy($calendarEvent);

        return redirect()->route('calendar.index')->with('status', 'Event deleted.');
    }

    public function events(Request $request): JsonResponse
    {
        $instituteId = $request->user()->institute_id;
        $start = $request->query('start');
        $end = $request->query('end');

        if (! $start || ! $end) {
            return response()->json([]);
        }

        $events = $this->service->queryRange(
            $instituteId,
            $start,
            $end,
            $request->query('branch_id') ? (int) $request->query('branch_id') : null,
            $request->query('teacher_id') ? (int) $request->query('teacher_id') : null,
            $request->query('batch_id') ? (int) $request->query('batch_id') : null,
            $request->query('room_id') ? (int) $request->query('room_id') : null,
            $request->query('course_id') ? (int) $request->query('course_id') : null,
            $request->query('subject_id') ? (int) $request->query('subject_id') : null,
            $request->query('event_type'),
            $request->query('academic_year_id') ? (int) $request->query('academic_year_id') : null,
        );

        $mapped = $events->map(fn ($event) => [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->is_all_day
                ? $event->start_date->format('Y-m-d')
                : $event->start_date->format('Y-m-d').'T'.($event->start_time ?? '00:00'),
            'end' => $event->is_all_day
                ? $event->start_date->copy()->addDay()->format('Y-m-d')
                : ($event->end_date ?? $event->start_date)->format('Y-m-d').'T'.($event->end_time ?? '23:59'),
            'allDay' => $event->is_all_day,
            'color' => $this->eventTypeColor($event->event_type),
            'extendedProps' => [
                'event_type' => $event->event_type,
                'course' => $event->course?->name,
                'batch' => $event->batch?->name,
                'teacher' => $event->teacher ? trim($event->teacher->first_name.' '.$event->teacher->last_name) : null,
                'room' => $event->room?->name,
                'subject' => $event->subject?->name,
                'branch' => $event->branch?->name,
            ],
        ]);

        return response()->json($mapped);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if ($q === '') {
            return response()->json([]);
        }

        $events = $this->service->search(
            $request->user()->institute_id,
            $q,
            $request->query('branch_id') ? (int) $request->query('branch_id') : null,
        );

        return response()->json($events->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'start_date' => $e->start_date->format('Y-m-d'),
            'event_type' => $e->event_type,
            'course' => $e->course?->name,
            'batch' => $e->batch?->name,
            'teacher' => $e->teacher ? trim($e->teacher->first_name.' '.$e->teacher->last_name) : null,
        ]));
    }

    public function timetable(Request $request): View
    {
        $instituteId = $request->user()->institute_id;
        $startDate = $request->query('start_date', now()->startOfWeek()->toDateString());
        $endDate = $request->query('end_date', now()->endOfWeek()->toDateString());

        $timetable = $this->service->timetable(
            $instituteId,
            $startDate,
            $endDate,
            $request->query('branch_id') ? (int) $request->query('branch_id') : null,
            $request->query('batch_id') ? (int) $request->query('batch_id') : null,
            $request->query('teacher_id') ? (int) $request->query('teacher_id') : null,
            $request->query('room_id') ? (int) $request->query('room_id') : null,
        );

        return view('calendar.timetable', [
            'timetable' => $timetable,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'branches' => Branch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'batches' => Batch::where('institute_id', $instituteId)->orderBy('name')->get(),
            'teachers' => InstituteUser::where('institute_id', $instituteId)->orderBy('first_name')->get(),
            'rooms' => Room::where('institute_id', $instituteId)->orderBy('name')->get(),
            'filters' => $request->only(['branch_id', 'batch_id', 'teacher_id', 'room_id']),
        ]);
    }

    private function eventTypeColor(string $type): string
    {
        return match ($type) {
            'class' => '#3788d8',
            'exam' => '#dc3545',
            'practical' => '#6f42c1',
            'viva' => '#6f42c1',
            'assignment' => '#fd7e14',
            'holiday' => '#198754',
            'training' => '#0dcaf0',
            'meeting' => '#6c757d',
            'academic_event' => '#0d6efd',
            'submission_deadline' => '#ffc107',
            'result_publication' => '#198754',
            'certificate_event' => '#b8860b',
            'other' => '#adb5bd',
            default => '#3788d8',
        };
    }
}
