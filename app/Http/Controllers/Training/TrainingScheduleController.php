<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrainingScheduleController extends Controller
{
    public function store(Request $request, $batchId)
    {
        $batch = TrainingBatch::findOrFail($batchId);

        $data = $this->validated($request);
        $apply = $this->applyFrom($request, $batch);

        $data['batch_id'] = $batch->id;
        $data['institute_id'] = auth()->user()->institute_id;
        // Effective-dated plan: the class joins the version in effect on the
        // week being edited; earlier dates keep their plan.
        $data['effective_from'] = $apply;
        $data['effective_to'] = null;

        TrainingSchedule::create($data);

        [$min] = $this->applyBounds($batch);

        return $this->back($apply > $min
            ? 'Class added from '.$apply.' - earlier dates keep the previous routine.'
            : 'Class added to the weekly schedule.', $request, $this->visibleWeek($apply, (int) $data['day_of_week']));
    }

    public function update(Request $request, $batchId, $scheduleId)
    {
        $batch = TrainingBatch::findOrFail($batchId);
        $schedule = TrainingSchedule::where('batch_id', $batch->id)->findOrFail($scheduleId);

        $data = $this->validated($request);
        $apply = $this->applyFrom($request, $batch);
        [$min] = $this->applyBounds($batch);

        $bornInWindow = $schedule->effective_from !== null && $schedule->effective_from >= $apply;

        if ($bornInWindow) {
            // Row only exists from the edited window onwards: edit in place.
            $schedule->update($data);
        } else {
            // Split: history keeps the old values up to the day before the
            // edited week; a new version carries the changes from the week on.
            DB::transaction(function () use ($schedule, $batch, $data, $apply) {
                $schedule->update(['effective_to' => $this->endBefore($schedule, $apply)]);

                TrainingSchedule::create($data + [
                    'batch_id' => $batch->id,
                    'institute_id' => $schedule->institute_id,
                    'effective_from' => $apply,
                    'effective_to' => null,
                ]);
            });
        }

        return $this->back($apply > $min
            ? 'Weekly schedule updated from '.$apply.' - earlier dates keep the previous routine.'
            : 'Weekly schedule updated.', $request, $this->visibleWeek($apply, (int) $data['day_of_week']));
    }

    public function destroy(Request $request, $batchId, $scheduleId)
    {
        $batch = TrainingBatch::findOrFail($batchId);
        $schedule = TrainingSchedule::where('batch_id', $batch->id)->findOrFail($scheduleId);

        $apply = $this->applyFrom($request, $batch);
        [$min] = $this->applyBounds($batch);

        if ($schedule->effective_from !== null && $schedule->effective_from >= $apply) {
            // Only ever existed inside the edited window: nothing historical.
            $schedule->delete();
        } else {
            // End it the day before the edited week so earlier dates keep the class.
            $schedule->update(['effective_to' => $this->endBefore($schedule, $apply)]);
        }

        return $this->back($apply > $min
            ? 'Class removed from '.$apply.' - it stays in earlier dates.'
            : 'Class removed from the weekly schedule.', $request, $this->visibleWeek($apply, (int) $schedule->day_of_week));
    }

    /**
     * The date the edit applies from: the start of the week being edited,
     * clamped inside the batch's own period (batch start..end). Batches
     * without dates fall back to today so nothing is ever backdated.
     */
    private function applyFrom(Request $request, TrainingBatch $batch): string
    {
        [$min, $max] = $this->applyBounds($batch);

        try {
            $apply = is_string($request->input('apply_from'))
                ? Carbon::parse($request->input('apply_from'))->toDateString()
                : now()->toDateString();
        } catch (\Throwable $e) {
            $apply = now()->toDateString();
        }

        if ($apply < $min) {
            return $min;
        }

        if ($max !== null && $apply > $max) {
            return $max;
        }

        return $apply;
    }

    /**
     * The batch's editable window as week starts: from the Monday of the
     * batch's start week to the Monday of its end week (null max = open).
     */
    private function applyBounds(TrainingBatch $batch): array
    {
        $min = $batch->start_date
            ? Carbon::parse($batch->start_date)->startOfWeek(Carbon::MONDAY)->toDateString()
            : now()->toDateString();
        $max = $batch->end_date
            ? Carbon::parse($batch->end_date)->startOfWeek(Carbon::MONDAY)->toDateString()
            : null;

        return [$min, ($max !== null && $max < $min) ? null : $max];
    }

    /**
     * Last date this row's current version covers (= day before the edit
     * applies), never extending an end date that is already earlier.
     */
    private function endBefore(TrainingSchedule $schedule, string $apply): string
    {
        $end = Carbon::parse($apply)->subDay()->toDateString();

        if ($schedule->effective_to !== null && $schedule->effective_to < $end) {
            return $schedule->effective_to;
        }

        return $end;
    }

    /**
     * The week in which a change first becomes visible: the earliest day on
     * or after the apply date that falls on the class's day of the week.
     * Re-opening the modal there shows the result instead of an unchanged
     * history view (which invited duplicate retry submissions).
     */
    private function visibleWeek(string $apply, int $dayOfWeek): string
    {
        $date = Carbon::parse($apply);

        for ($i = 0; $i < 7; $i++) {
            if (((int) $date->dayOfWeek + 6) % 7 === $dayOfWeek) {
                break;
            }
            $date->addDay();
        }

        return $date->toDateString();
    }

    private function validated(Request $request): array
    {
        $instituteId = auth()->user()->institute_id;

        return $request->validate([
            'subject_id' => [
                'nullable', 'integer',
                Rule::exists('training_subjects', 'id')
                    ->where('institute_id', $instituteId)
                    ->whereNull('deleted_at'),
            ],
            'title' => 'nullable|string|max:150',
            'day_of_week' => 'required|integer|between:0,6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:80',
            'apply_from' => 'nullable|date_format:Y-m-d',
        ]);
    }

    private function back(string $message, Request $request, ?string $scheduleWeek = null)
    {
        return redirect()->back()->with([
            'success' => $message,
            'schedule_open' => 1,
            // Re-open the modal on the first week where the change is visible.
            'schedule_week' => $scheduleWeek ?? $request->input('apply_from'),
        ]);
    }
}
