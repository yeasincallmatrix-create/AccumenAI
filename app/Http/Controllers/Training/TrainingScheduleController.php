<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingScheduleController extends Controller
{
    public function store(Request $request, $batchId)
    {
        $batch = TrainingBatch::findOrFail($batchId);

        $data = $this->validated($request);
        $data['batch_id'] = $batch->id;
        $data['institute_id'] = auth()->user()->institute_id;
        // Live plan: new classes join today's version. Older dates keep the plan
        // they were made under (effective-dated history).
        $data['effective_from'] = now()->toDateString();
        $data['effective_to'] = null;

        TrainingSchedule::create($data);

        return $this->back('Class added to the weekly schedule.');
    }

    public function update(Request $request, $batchId, $scheduleId)
    {
        $batch = TrainingBatch::findOrFail($batchId);
        $schedule = TrainingSchedule::where('batch_id', $batch->id)->findOrFail($scheduleId);

        $data = $this->validated($request);
        $today = now()->toDateString();

        $bornToday = $schedule->effective_from !== null && $schedule->effective_from >= $today;

        if ($bornToday) {
            // Created today → no history to preserve, edit the row in place.
            $schedule->update($data);
        } else {
            // Split the version: history keeps the old values up to yesterday,
            // a new version carries the changes from today onwards (real-time).
            $schedule->update(['effective_to' => $this->endOfCurrentVersion($schedule)]);

            TrainingSchedule::create($data + [
                'batch_id' => $batch->id,
                'institute_id' => $schedule->institute_id,
                'effective_from' => $today,
                'effective_to' => null,
            ]);
        }

        return $this->back('Weekly schedule updated.');
    }

    public function destroy($batchId, $scheduleId)
    {
        $batch = TrainingBatch::findOrFail($batchId);
        $schedule = TrainingSchedule::where('batch_id', $batch->id)->findOrFail($scheduleId);

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        if ($schedule->effective_from !== null && $schedule->effective_from > $yesterday) {
            // Only existed from today → nothing historical to keep.
            $schedule->delete();
        } else {
            // End-date from today so past dates keep the class in their plan version.
            $schedule->update(['effective_to' => $this->endOfCurrentVersion($schedule)]);
        }

        return $this->back('Class removed from the weekly schedule.');
    }

    /**
     * The date this row's current version stops covering (= yesterday), never
     * extending an end date that is already earlier.
     */
    private function endOfCurrentVersion(TrainingSchedule $schedule): string
    {
        $yesterday = now()->subDay()->toDateString();

        if ($schedule->effective_to !== null && $schedule->effective_to < $yesterday) {
            return $schedule->effective_to;
        }

        return $yesterday;
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
        ]);
    }

    private function back(string $message)
    {
        return redirect()->back()->with([
            'success' => $message,
            'schedule_open' => 1,
        ]);
    }
}
