<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarEventReminder;
use Illuminate\Support\Collection;

/**
 * Core service for calendar event CRUD, conflict detection, and querying.
 */
class CalendarEventService
{
    public function __construct(
        private readonly RecurrenceService $recurrence,
    ) {}

    public const EVENT_TYPES = [
        'class',
        'exam',
        'practical',
        'viva',
        'assignment',
        'holiday',
        'training',
        'meeting',
        'academic_event',
        'submission_deadline',
        'result_publication',
        'certificate_event',
        'other',
    ];

    public function create(array $data): CalendarEvent
    {
        $event = CalendarEvent::create($data);

        if (! empty($data['recurrence_rule'])) {
            $this->recurrence->generateOccurrences($event);
        }

        return $event->fresh();
    }

    public function update(CalendarEvent $event, array $data): CalendarEvent
    {
        $hadRecurrence = $event->isRecurring();
        $event->update($data);

        if (! empty($data['recurrence_rule'])) {
            $event->childEvents()->delete();
            $this->recurrence->generateOccurrences($event);
        } elseif ($hadRecurrence && empty($event->recurrence_rule)) {
            $event->childEvents()->delete();
        }

        return $event->fresh();
    }

    public function destroy(CalendarEvent $event): void
    {
        $event->childEvents()->delete();
        $event->reminders()->delete();
        $event->delete();
    }

    public function find(int $id): ?CalendarEvent
    {
        return CalendarEvent::with(['course', 'batch', 'teacher', 'room', 'subject', 'branch', 'academicYear', 'classGrade'])->find($id);
    }

    /**
     * Query events for a date range with optional filters.
     */
    public function queryRange(
        int $instituteId,
        string $startDate,
        string $endDate,
        ?int $branchId = null,
        ?int $teacherId = null,
        ?int $batchId = null,
        ?int $roomId = null,
        ?int $courseId = null,
        ?int $subjectId = null,
        ?string $eventType = null,
        ?int $academicYearId = null,
    ): Collection {
        return CalendarEvent::query()
            ->with(['course', 'batch', 'teacher', 'room', 'subject', 'branch'])
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q2) use ($startDate, $endDate) {
                    $q2->where('start_date', '>=', $startDate)
                        ->where('start_date', '<=', $endDate);
                });
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($teacherId, fn ($q) => $q->where('teacher_id', $teacherId))
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->when($roomId, fn ($q) => $q->where('room_id', $roomId))
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId))
            ->when($eventType, fn ($q) => $q->where('event_type', $eventType))
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Detect scheduling conflicts for teacher, room, or batch.
     * Returns array of conflicting events.
     */
    public function detectConflicts(
        int $instituteId,
        string $startDate,
        ?string $startTime,
        ?string $endTime,
        bool $isAllDay = false,
        ?int $teacherId = null,
        ?int $roomId = null,
        ?int $batchId = null,
        ?int $excludeEventId = null,
    ): array {
        $conflicts = [];

        if ($isAllDay) {
            $query = CalendarEvent::query()
                ->where('institute_id', $instituteId)
                ->where('status', 'active')
                ->where('is_all_day', true)
                ->where('start_date', $startDate);

            if ($teacherId) {
                $teacherConflicts = (clone $query)->where('teacher_id', $teacherId);
                if ($excludeEventId) {
                    $teacherConflicts->where('id', '!=', $excludeEventId);
                }
                $conflicts['teacher'] = $teacherConflicts->get();
            }

            if ($roomId) {
                $roomConflicts = (clone $query)->where('room_id', $roomId);
                if ($excludeEventId) {
                    $roomConflicts->where('id', '!=', $excludeEventId);
                }
                $conflicts['room'] = $roomConflicts->get();
            }

            if ($batchId) {
                $batchConflicts = (clone $query)->where('batch_id', $batchId);
                if ($excludeEventId) {
                    $batchConflicts->where('id', '!=', $excludeEventId);
                }
                $conflicts['batch'] = $batchConflicts->get();
            }

            return $conflicts;
        }

        if ($startTime === null || $endTime === null) {
            return $conflicts;
        }

        $query = CalendarEvent::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->where('is_all_day', false)
            ->where('start_date', $startDate)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($teacherId) {
            $teacherConflicts = (clone $query)->where('teacher_id', $teacherId);
            if ($excludeEventId) {
                $teacherConflicts->where('id', '!=', $excludeEventId);
            }
            $conflicts['teacher'] = $teacherConflicts->get();
        }

        if ($roomId) {
            $roomConflicts = (clone $query)->where('room_id', $roomId);
            if ($excludeEventId) {
                $roomConflicts->where('id', '!=', $excludeEventId);
            }
            $conflicts['room'] = $roomConflicts->get();
        }

        if ($batchId) {
            $batchConflicts = (clone $query)->where('batch_id', $batchId);
            if ($excludeEventId) {
                $batchConflicts->where('id', '!=', $excludeEventId);
            }
            $conflicts['batch'] = $batchConflicts->get();
        }

        return $conflicts;
    }

    public function hasConflicts(array $conflicts): bool
    {
        return collect($conflicts)->some(fn ($items) => $items->isNotEmpty());
    }

    /**
     * Search events by keyword across title, course, batch, teacher, room.
     */
    public function search(int $instituteId, string $query, ?int $branchId = null): Collection
    {
        return CalendarEvent::query()
            ->with(['course', 'batch', 'teacher', 'room', 'subject', 'branch'])
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhereHas('course', fn ($cq) => $cq->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('batch', fn ($bq) => $bq->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('teacher', fn ($tq) => $tq->where('first_name', 'like', "%{$query}%")->orWhere('last_name', 'like', "%{$query}%"))
                    ->orWhereHas('room', fn ($rq) => $rq->where('name', 'like', "%{$query}%"))
                    ->orWhereHas('subject', fn ($sq) => $sq->where('name', 'like', "%{$query}%"));
            })
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->limit(50)
            ->get();
    }

    /**
     * Get timetable-style data: events grouped by date for a given scope.
     */
    public function timetable(
        int $instituteId,
        string $startDate,
        string $endDate,
        ?int $branchId = null,
        ?int $batchId = null,
        ?int $teacherId = null,
        ?int $roomId = null,
    ): Collection {
        $events = $this->queryRange(
            $instituteId,
            $startDate,
            $endDate,
            $branchId,
            $teacherId,
            $batchId,
            $roomId,
        );

        return $events->groupBy(fn ($event) => $event->start_date->format('Y-m-d'));
    }

    /**
     * Create a reminder for an event.
     */
    public function addReminder(CalendarEvent $event, int $userId, int $minutesBefore = 30, string $type = 'notification'): CalendarEventReminder
    {
        return CalendarEventReminder::create([
            'event_id' => $event->id,
            'user_id' => $userId,
            'reminder_type' => $type,
            'minutes_before' => $minutesBefore,
        ]);
    }
}
