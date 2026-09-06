<?php

namespace App\Services\Medical;

use App\Models\Medical\Admission;
use App\Models\Medical\Bed;
use App\Models\Medical\Ward;
use Illuminate\Support\Facades\DB;

/**
 * Allocate, release and transfer IPD beds with ward-counter bookkeeping.
 *
 * Phase 2 adaptation: every method takes the institute id and scopes all
 * reads/writes to it (the spec version was unscoped, allowing cross-tenant
 * bed allocation). Rows are locked inside transactions so two concurrent
 * admissions cannot take the same bed.
 */
class BedAllocationService
{
    /**
     * Find an available bed in a specific ward type.
     */
    public function findAvailableBed(int $instituteId, string $wardType): ?Bed
    {
        return Bed::where('institute_id', $instituteId)
            ->whereHas('ward', function ($q) use ($wardType, $instituteId) {
                $q->where('institute_id', $instituteId)
                    ->where('type', $wardType)
                    ->where('is_active', true);
            })
            ->where('status', 'available')
            ->first();
    }

    /**
     * Find available beds with capacity information.
     */
    public function getAvailableBedsByType(int $instituteId): array
    {
        $wards = Ward::where('institute_id', $instituteId)->where('is_active', true)->get();
        $result = [];

        foreach ($wards as $ward) {
            $available = $ward->beds()->where('status', 'available')->count();
            $result[] = [
                'ward' => $ward,
                'available_beds' => $available,
                'total_beds' => $ward->total_beds,
                'occupancy_rate' => $ward->occupancy_rate,
            ];
        }

        return $result;
    }

    /**
     * Allocate a bed to an admission.
     */
    public function allocateBed(int $instituteId, int $bedId, int $admissionId): bool
    {
        return DB::transaction(function () use ($instituteId, $bedId, $admissionId) {
            $bed = Bed::where('institute_id', $instituteId)->lockForUpdate()->findOrFail($bedId);

            if ($bed->status !== 'available') {
                throw new \RuntimeException('Bed is not available.');
            }

            $bed->update(['status' => 'occupied']);

            // Update ward available beds count.
            $ward = $bed->ward;
            $ward->decrement('available_beds');

            // Link bed to admission.
            $admission = Admission::where('institute_id', $instituteId)->findOrFail($admissionId);
            $admission->update(['bed_id' => $bedId]);

            return true;
        });
    }

    /**
     * Release a bed (discharge or transfer).
     */
    public function releaseBed(int $instituteId, int $bedId): bool
    {
        return DB::transaction(function () use ($instituteId, $bedId) {
            $bed = Bed::where('institute_id', $instituteId)->lockForUpdate()->findOrFail($bedId);

            if ($bed->status !== 'occupied') {
                throw new \RuntimeException('Bed is not occupied.');
            }

            $bed->update(['status' => 'available']);

            // Update ward available beds count.
            $ward = $bed->ward;
            $ward->increment('available_beds');

            return true;
        });
    }

    /**
     * Transfer a patient to another bed.
     */
    public function transferBed(int $instituteId, int $fromBedId, int $toBedId): bool
    {
        return DB::transaction(function () use ($instituteId, $fromBedId, $toBedId) {
            if ($fromBedId === $toBedId) {
                throw new \RuntimeException('Source and target beds are the same.');
            }

            $fromBed = Bed::where('institute_id', $instituteId)->lockForUpdate()->findOrFail($fromBedId);
            $toBed = Bed::where('institute_id', $instituteId)->lockForUpdate()->findOrFail($toBedId);

            if ($fromBed->status !== 'occupied') {
                throw new \RuntimeException('Source bed is not occupied.');
            }

            if ($toBed->status !== 'available') {
                throw new \RuntimeException('Target bed is not available.');
            }

            // Get the admission linked to the from bed.
            $admission = Admission::where('institute_id', $instituteId)
                ->where('bed_id', $fromBedId)
                ->where('status', 'active')
                ->first();

            if (! $admission) {
                throw new \RuntimeException('No active admission found for this bed.');
            }

            // Release from bed.
            $fromBed->update(['status' => 'available']);
            $fromBed->ward->increment('available_beds');

            // Allocate to bed.
            $toBed->update(['status' => 'occupied']);
            $toBed->ward->decrement('available_beds');

            // Update admission.
            $admission->update(['bed_id' => $toBedId]);

            return true;
        });
    }

    /**
     * Get ward occupancy summary.
     */
    public function getOccupancySummary(int $instituteId): array
    {
        $wards = Ward::where('institute_id', $instituteId)->where('is_active', true)->get();

        return $wards->map(function ($ward) {
            return [
                'name' => $ward->name,
                'type' => $ward->type,
                'total_beds' => $ward->total_beds,
                'available_beds' => $ward->available_beds,
                'occupied_beds' => $ward->total_beds - $ward->available_beds,
                'occupancy_rate' => $ward->occupancy_rate,
            ];
        })->toArray();
    }
}
