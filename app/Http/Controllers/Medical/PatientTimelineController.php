<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Patient;
use App\Models\Medical\PatientTimelineEvent;
use App\Services\Medical\PatientTimelineEventService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PatientTimelineController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.records.timeline.view', only: ['show', 'data']),
            new Middleware('permission:medical.records.view', only: ['backfill']),
        ];
    }

    public function __construct(
        private readonly PatientTimelineEventService $timeline,
    ) {}

    public function show(Request $request, Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');
        $this->ensurePatientVisible($patient);

        $instituteId = $this->instituteId();
        $filters = [
            'event_types' => $request->filled('event_types') ? (array) $request->input('event_types') : [],
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        $events = $this->timeline->getTimeline($instituteId, $patient->id, $filters);
        $eventTypes = PatientTimelineEvent::EVENT_TYPES;

        return view('medical.records.timeline.show', compact('patient', 'events', 'eventTypes', 'filters'));
    }

    public function data(Request $request, Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');

        $instituteId = $this->instituteId();
        $filters = [
            'event_types' => $request->filled('event_types') ? (array) $request->input('event_types') : [],
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        return response()->json([
            'events' => $this->timeline->getTimeline($instituteId, $patient->id, $filters)->values(),
        ]);
    }

    public function backfill(Patient $patient)
    {
        $this->ensureSameInstitute($patient, 'patient');

        $count = $this->timeline->backfillForPatient($this->instituteId(), $patient->id);

        return redirect()
            ->route('medical.records.patients.timeline', $patient)
            ->with('status', "Timeline backfilled: {$count} events created.");
    }
}
