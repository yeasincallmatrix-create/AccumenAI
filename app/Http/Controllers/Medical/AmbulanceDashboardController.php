<?php

namespace App\Http\Controllers\Medical;

use App\Models\Medical\Ambulance;
use App\Models\Medical\AmbulanceTrip;
use App\Services\Medical\AmbulanceDispatchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AmbulanceDashboardController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical.ambulance.view', only: ['index', 'dispatchBoard']),
        ];
    }

    public function __construct(
        private readonly AmbulanceDispatchService $dispatch,
    ) {}

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();

        $fleet = $this->dispatch->fleetSummary($instituteId);

        $activeTrips = AmbulanceTrip::forInstitute($instituteId)
            ->active()
            ->with(['ambulance', 'driver', 'patient'])
            ->orderByDesc('ambulance_trips.requested_at')
            ->limit(15)
            ->get();

        $pendingTrips = AmbulanceTrip::forInstitute($instituteId)
            ->pending()
            ->with(['patient'])
            ->orderByDesc('ambulance_trips.requested_at')
            ->limit(10)
            ->get();

        $todayStats = [
            'trips_today' => AmbulanceTrip::forInstitute($instituteId)->today()->count(),
            'completed_today' => AmbulanceTrip::forInstitute($instituteId)->today()->completed()->count(),
            'revenue_today' => (float) AmbulanceTrip::forInstitute($instituteId)->today()->completed()->sum('ambulance_trips.total_fee'),
        ];

        return view('medical.ambulance.dashboard', compact('fleet', 'activeTrips', 'pendingTrips', 'todayStats'));
    }

    public function dispatchBoard(Request $request)
    {
        $instituteId = $this->instituteId();

        $pending = AmbulanceTrip::forInstitute($instituteId)
            ->pending()
            ->with(['patient'])
            ->orderBy('ambulance_trips.priority')
            ->orderBy('ambulance_trips.requested_at')
            ->get();

        $active = AmbulanceTrip::forInstitute($instituteId)
            ->active()
            ->with(['ambulance', 'driver', 'patient'])
            ->orderByDesc('ambulance_trips.requested_at')
            ->get();

        $availableAmbulances = $this->dispatch->findAvailableAmbulances($instituteId);
        $availableDrivers = $this->dispatch->findAvailableDrivers($instituteId);

        return view('medical.ambulance.dispatch-board', compact('pending', 'active', 'availableAmbulances', 'availableDrivers'));
    }
}
