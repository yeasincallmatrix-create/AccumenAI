<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\LabOrder;
use App\Services\Medical\LabService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Lab landing page (route: GET lab → medical.lab.index).
 *
 * Catalog CRUD lives on LabTestController and orders on LabOrderController
 * (per the lab/* routes); this controller is the dashboard shell.
 */
class LabController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_lab.view', only: ['index']),
        ];
    }

    protected LabService $labService;

    public function __construct(LabService $labService)
    {
        $this->labService = $labService;
    }

    public function index()
    {
        $instituteId = $this->instituteId();

        $pending = $this->labService->getPendingOrders($instituteId);

        $recentCompleted = LabOrder::where('institute_id', $instituteId)
            ->where('status', 'completed')
            ->with(['patient'])
            ->orderBy('completed_at', 'desc')
            ->limit(10)
            ->get();

        return view('medical.lab.dashboard', compact('pending', 'recentCompleted'));
    }
}
