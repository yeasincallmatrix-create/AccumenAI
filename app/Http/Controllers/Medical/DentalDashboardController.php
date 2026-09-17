<?php

namespace App\Http\Controllers\Medical;

use App\Services\Medical\DentalService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DentalDashboardController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.dental.view', only: ['index']),
        ];
    }

    public function __construct(
        private readonly DentalService $dentalService,
    ) {}

    public function index()
    {
        $instituteId = $this->instituteId();
        $branchId = $this->branchContextId();

        $todayProcedures = $this->dentalService->todayProcedures($instituteId, $branchId);
        $activePlansCount = $this->dentalService->activePlansCount($instituteId, $branchId);
        $upcomingFollowUps = $this->dentalService->upcomingFollowUps($instituteId, $branchId);

        return view('medical.dental.dashboard', compact(
            'todayProcedures',
            'activePlansCount',
            'upcomingFollowUps',
        ));
    }
}
