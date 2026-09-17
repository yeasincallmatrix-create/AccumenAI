<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\BloodDonor;
use App\Models\Medical\BloodRequest;
use App\Models\Medical\BloodUnit;
use App\Services\Medical\BloodBankService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class BloodBankDashboardController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_bloodbank.view', only: ['dashboard']),
        ];
    }

    public function __construct(private readonly BloodBankService $bloodBankService) {}

    public function dashboard()
    {
        $instituteId = $this->instituteId();
        $branchId = $this->branchContextId();

        $stockSummary = $this->bloodBankService->stockSummary($instituteId, $branchId);
        $availableByGroup = $this->bloodBankService->availableByBloodGroup($instituteId, $branchId);
        $lowStock = $this->bloodBankService->lowStock($instituteId, 5, $branchId);
        $expiringSoon = $this->bloodBankService->expiringSoon($instituteId, 7, $branchId);

        $todayStats = [
            'total_donors' => BloodDonor::where('institute_id', $instituteId)->count(),
            'active_units' => BloodUnit::where('institute_id', $instituteId)->where('status', 'available')->where('expiry_date', '>', now())->count(),
            'pending_requests' => BloodRequest::where('institute_id', $instituteId)->where('status', 'pending')->count(),
            'fulfilled_today' => BloodRequest::where('institute_id', $instituteId)->where('status', 'fulfilled')->whereDate('fulfilled_at', today())->count(),
            'urgent_requests' => BloodRequest::where('institute_id', $instituteId)->whereIn('status', ['pending', 'approved'])->where('urgency', 'emergent')->count(),
            'expired_units' => BloodUnit::where('institute_id', $instituteId)->where('status', 'available')->where('expiry_date', '<', now())->count(),
        ];

        return view('medical.blood-bank.dashboard', compact('stockSummary', 'availableByGroup', 'lowStock', 'expiringSoon', 'todayStats'));
    }
}
