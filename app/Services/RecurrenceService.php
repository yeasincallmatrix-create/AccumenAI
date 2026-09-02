<?php

namespace App\Services;

use App\Models\CalendarEvent;
use Illuminate\Support\Carbon;

/**
 * Generates recurring event occurrences from a master event's recurrence_rule.
 *
 * Recurrence rule format (stored as JSON):
 * [
 *     "frequency" => "daily|weekly|monthly",
 *     "interval" => 1,              // every N days/weeks/months
 *     "days_of_week" => ["mon","tue","wed"],  // for weekly
 *     "end_date" => "2026-12-31",   // null = no end
 *     "max_occurrences" => 100,     // safety cap
 * ]
 */
class RecurrenceService
{
    private const MAX_OCCURRENCES = 200;

    public function generateOccurrences(CalendarEvent $master): void
    {
        $rule = $master->recurrence_rule;
        if (empty($rule)) {
            return;
        }

        $frequency = $rule['frequency'] ?? 'weekly';
        $interval = max(1, (int) ($rule['interval'] ?? 1));
        $daysOfWeek = $rule['days_of_week'] ?? [];
        $endDate = isset($rule['end_date']) ? Carbon::parse($rule['end_date']) : null;
        $maxOccurrences = min((int) ($rule['max_occurrences'] ?? self::MAX_OCCURRENCES), self::MAX_OCCURRENCES);

        $current = Carbon::parse($master->start_date)->addDays($interval);
        $end = $endDate ?? Carbon::now()->addYears(2);
        $count = 0;

        while ($current->lte($end) && $count < $maxOccurrences) {
            if ($frequency === 'daily') {
                $this->createOccurrence($master, $current);
                $count++;
                $current = $current->addDays($interval);
            } elseif ($frequency === 'weekly') {
                if (empty($daysOfWeek)) {
                    $this->createOccurrence($master, $current);
                    $count++;
                    $current = $current->addWeeks($interval);
                } else {
                    $weekStart = $current->copy()->startOfWeek();
                    for ($day = 0; $day < 7; $day++) {
                        $dayDate = $weekStart->copy()->addDays($day);
                        if ($dayDate->lte($master->start_date)) {
                            continue;
                        }
                        if ($dayDate->gt($end)) {
                            break 2;
                        }
                        $dayName = strtolower($dayDate->format('D'));
                        if (in_array($dayName, $daysOfWeek, true)) {
                            $this->createOccurrence($master, $dayDate);
                            $count++;
                            if ($count >= $maxOccurrences) {
                                break 2;
                            }
                        }
                    }
                    $current = $current->addWeeks($interval);
                }
            } elseif ($frequency === 'monthly') {
                $this->createOccurrence($master, $current);
                $count++;
                $current = $current->addMonths($interval);
            }
        }
    }

    private function createOccurrence(CalendarEvent $master, Carbon $date): void
    {
        CalendarEvent::create([
            'institute_id' => $master->institute_id,
            'branch_id' => $master->branch_id,
            'event_type' => $master->event_type,
            'title' => $master->title,
            'description' => $master->description,
            'start_date' => $date->toDateString(),
            'start_time' => $master->start_time,
            'end_date' => $date->toDateString(),
            'end_time' => $master->end_time,
            'is_all_day' => $master->is_all_day,
            'course_id' => $master->course_id,
            'subject_id' => $master->subject_id,
            'batch_id' => $master->batch_id,
            'class_grade_id' => $master->class_grade_id,
            'academic_group_id' => $master->academic_group_id,
            'teacher_id' => $master->teacher_id,
            'room_id' => $master->room_id,
            'academic_year_id' => $master->academic_year_id,
            'created_by' => $master->created_by,
            'status' => 'active',
            'parent_event_id' => $master->id,
        ]);
    }
}
