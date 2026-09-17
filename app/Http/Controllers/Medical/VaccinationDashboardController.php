<?php

namespace App\Http\Controllers\Medical;

use App\Services\Medical\VaccinationService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class VaccinationDashboardController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.vaccination.view', only: ['index']),
        ];
    }

    public function __construct(
        private readonly VaccinationService $vaccinationService,
    ) {}

    public function index()
    {
        $instituteId = $this->instituteId();
        $branchId = $this->branchContextId();

        $dueToday = $this->vaccinationService->getDueToday($instituteId, $branchId);
        $overdue = $this->vaccinationService->getOverdue($instituteId, $branchId);
        $lowStockCount = $this->vaccinationService->lowStockCount($instituteId, $branchId);
        $expiringStocks = $this->vaccinationService->expiringStocks($instituteId, $branchId, 30);

        return view('medical.vaccination.dashboard', compact(
            'dueToday',
            'overdue',
            'lowStockCount',
            'expiringStocks',
        ));
    }
}
