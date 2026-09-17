<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\PhysiotherapyPlan;
use App\Services\Medical\PhysiotherapyService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PhysiotherapyDashboardController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.physiotherapy.view', only: ['index']),
        ];
    }

    public function __construct(private readonly PhysiotherapyService $physioService) {}

    public function index()
    {
        $instituteId = $this->instituteId();
        $branchId = $this->branchContextId();
        $todaySessions = $this->physioService->todaySessions($instituteId, $branchId);
        $activePlansCount = $this->physioService->activePlansCount($instituteId, $branchId);
        $attendedToday = $this->physioService->todaysAttendedCount($instituteId, $branchId);
        $plans = PhysiotherapyPlan::where('institute_id', $instituteId)->where('status', 'active')
            ->with(['patient', 'therapist'])->latest()->get();
        $completedPlans = PhysiotherapyPlan::where('institute_id', $instituteId)->where('status', 'completed')->count();
        $pendingSessions = \App\Models\Medical\PhysiotherapySession::where('institute_id', $instituteId)
            ->where('status', 'scheduled')->count();
        $user = auth('web')->user();

        return view('medical.physiotherapy.dashboard', compact(
            'todaySessions', 'activePlansCount', 'attendedToday', 'plans',
            'completedPlans', 'pendingSessions', 'user',
        ));
    }
}
