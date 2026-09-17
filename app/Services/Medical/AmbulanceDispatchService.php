<?php

namespace App\Services\Medical;

use App\Models\Medical\Ambulance;
use App\Models\Medical\AmbulanceDriver;
use App\Models\Medical\AmbulanceTrip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AmbulanceDispatchService
{
    public function findAvailableAmbulances(int $instituteId, ?string $type = null, ?int $branchId = null): Collection
    {
        return Ambulance::forInstitute($instituteId)
            ->when($branchId, fn ($q) => $q->where('ambulances.branch_id', $branchId))
            ->available()->active()
            ->when($type, fn ($q) => $q->where('ambulances.type', $type))
            ->orderByRaw("FIELD(type, 'advanced_life_support', 'mobile_icu', 'basic', 'neonatal', 'mortuary')")
            ->get();
    }

    public function findAvailableDrivers(int $instituteId, ?int $branchId = null): Collection
    {
        return AmbulanceDriver::forInstitute($instituteId)
            ->when($branchId, fn ($q) => $q->where('ambulance_drivers.branch_id', $branchId))
            ->active()->get()
            ->filter(fn ($d) => ! $d->trips()->whereIn('status', AmbulanceDriver::ACTIVE_TRIP_STATUSES)->exists())
            ->values();
    }

    public function dispatch(AmbulanceTrip $trip, Ambulance $ambulance, AmbulanceDriver $driver): void
    {
        if (! $ambulance->isAvailable()) {
            throw new \InvalidArgumentException('Ambulance is not available for dispatch.');
        }
        if ($driver->isOnActiveTrip()) {
            throw new \InvalidArgumentException('Driver is already on an active trip.');
        }

        DB::transaction(function () use ($trip, $ambulance, $driver) {
            $trip->update([
                'ambulance_id' => $ambulance->id,
                'driver_id' => $driver->id,
                'status' => 'dispatched',
                'dispatched_at' => now(),
            ]);
            $ambulance->update(['status' => 'dispatched']);
        });
    }

    public function updateStatus(AmbulanceTrip $trip, string $status): void
    {
        if (! array_key_exists($status, AmbulanceTrip::STATUSES)) {
            throw new \InvalidArgumentException("Unknown trip status [{$status}].");
        }

        $timestamps = [
            'at_pickup' => 'arrived_at_pickup',
            'en_route_to_dropoff' => 'departed_pickup',
            'arrived' => 'arrived_at_dropoff',
            'completed' => 'completed_at',
        ];

        DB::transaction(function () use ($trip, $status, $timestamps) {
            $update = ['status' => $status];
            if (isset($timestamps[$status])) {
                $update[$timestamps[$status]] = now();
            }
            $trip->update($update);

            if (in_array($status, AmbulanceTrip::FINAL_STATUSES, true)) {
                $trip->ambulance?->update(['status' => 'available']);
            } elseif ($trip->isActive()) {
                $trip->ambulance?->update(['status' => 'on_trip']);
            }

            if ($status === 'completed') {
                $trip->refresh();
                $computed = [];
                if ($trip->odometer_end_km && $trip->odometer_start_km) {
                    $computed['distance_km'] = round((float) $trip->odometer_end_km - (float) $trip->odometer_start_km, 2);
                }
                if ($trip->calculateDuration() !== null) {
                    $computed['duration_minutes'] = $trip->calculateDuration();
                }
                $computed['total_fee'] = $trip->calculateTotalFee();
                $trip->update($computed);
            }
        });
    }

    public function fleetSummary(int $instituteId): array
    {
        return [
            'total' => Ambulance::forInstitute($instituteId)->active()->count(),
            'available' => Ambulance::forInstitute($instituteId)->available()->count(),
            'dispatched' => Ambulance::forInstitute($instituteId)->dispatched()->count(),
            'on_trip' => Ambulance::forInstitute($instituteId)->onTrip()->count(),
            'maintenance' => Ambulance::forInstitute($instituteId)->inMaintenance()->count(),
        ];
    }

    public function estimateFare(Ambulance $ambulance, float $distanceKm, int $waitingMinutes = 0): array
    {
        $baseRates = [
            'basic' => 500,
            'advanced_life_support' => 1500,
            'mobile_icu' => 2500,
            'neonatal' => 2000,
            'mortuary' => 800,
        ];
        $base = $baseRates[$ambulance->type] ?? 500;
        $perKm = 30;
        $waitingPerMin = 5;

        return [
            'base_fee' => $base,
            'distance_fee' => $distanceKm * $perKm,
            'waiting_fee' => $waitingMinutes * $waitingPerMin,
            'total' => $base + ($distanceKm * $perKm) + ($waitingMinutes * $waitingPerMin),
        ];
    }
}
