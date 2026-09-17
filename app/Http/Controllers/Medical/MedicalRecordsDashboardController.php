<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\ClinicalNote;
use App\Models\Medical\DischargeSummary;
use App\Models\Medical\MedicalDocument;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientTimelineEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MedicalRecordsDashboardController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.records.view', only: ['index']),
        ];
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $docQuery = MedicalDocument::forInstitute($instituteId);
        $this->scopeBranch($docQuery);

        $noteQuery = ClinicalNote::forInstitute($instituteId);
        $this->scopeBranch($noteQuery);

        $summaryQuery = DischargeSummary::forInstitute($instituteId);
        $this->scopeBranch($summaryQuery);

        $eventQuery = PatientTimelineEvent::where('patient_timeline_events.institute_id', $instituteId);

        $stats = [
            'patients_with_records' => (clone $eventQuery)->distinct('patient_id')->count('patient_id'),
            'documents' => (clone $docQuery)->count(),
            'summaries' => (clone $summaryQuery)->count(),
            'notes' => (clone $noteQuery)->count(),
            'events_today' => (clone $eventQuery)->whereDate('patient_timeline_events.event_date', today())->count(),
        ];

        $recentActivity = PatientTimelineEvent::where('patient_timeline_events.institute_id', $instituteId)
            ->with(['patient', 'doctor'])
            ->orderByDesc('patient_timeline_events.event_at')
            ->limit(20)
            ->get();

        $recentDocuments = (clone $docQuery)->with(['patient'])
            ->orderByDesc('created_at')->limit(10)->get();

        $pendingFollowUps = (clone $summaryQuery)->with(['patient'])
            ->whereNotNull('follow_up_date')
            ->where('follow_up_date', '>=', today()->toDateString())
            ->orderBy('follow_up_date')->limit(10)->get();

        return view('medical.records.dashboard', compact('stats', 'recentActivity', 'recentDocuments', 'pendingFollowUps'));
    }
}
