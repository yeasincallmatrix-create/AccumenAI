<?php

namespace App\Services\Medical;

use App\Models\Medical\BloodDonor;
use App\Models\Medical\BloodIssueItem;
use App\Models\Medical\BloodRequest;
use App\Models\Medical\BloodUnit;
use App\Models\Medical\ClinicalAuditLog;
use App\Support\MedicalScope;
use Illuminate\Support\Facades\DB;

class BloodBankService
{
    public function stockSummary(int $instituteId, ?int $branchId = null): array
    {
        $query = BloodUnit::where('institute_id', $instituteId)
            ->where('status', 'available')
            ->where('expiry_date', '>', now());

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        $units = $query->get(['blood_group', 'component']);

        $summary = [];
        foreach ($units as $unit) {
            $summary[$unit->blood_group][$unit->component] = ($summary[$unit->blood_group][$unit->component] ?? 0) + 1;
        }

        return $summary;
    }

    public function availableByBloodGroup(int $instituteId, ?int $branchId = null): array
    {
        $query = BloodUnit::where('institute_id', $instituteId)
            ->where('status', 'available')
            ->where('expiry_date', '>', now());

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        return $query->selectRaw('blood_group, COUNT(*) as count')
            ->groupBy('blood_group')
            ->pluck('count', 'blood_group')
            ->toArray();
    }

    public function findCompatibleUnits(int $instituteId, string $bloodGroup, string $component, ?int $branchId = null)
    {
        $compatibility = [
            'O-'  => ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
            'O+'  => ['O+', 'A+', 'B+', 'AB+'],
            'A-'  => ['A-', 'A+', 'AB-', 'AB+'],
            'A+'  => ['A+', 'AB+'],
            'B-'  => ['B-', 'B+', 'AB-', 'AB+'],
            'B+'  => ['B+', 'AB+'],
            'AB-' => ['AB-', 'AB+'],
            'AB+' => ['AB+'],
        ];

        $compatibleGroups = $compatibility[$bloodGroup] ?? [];

        $query = BloodUnit::where('institute_id', $instituteId)
            ->where('status', 'available')
            ->where('expiry_date', '>', now())
            ->where('component', $component)
            ->whereIn('blood_group', $compatibleGroups);

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        return $query;
    }

    public function reserveUnit(BloodUnit $unit, int $patientId): bool
    {
        return DB::transaction(function () use ($unit, $patientId) {
            $unit->refresh();

            if ($unit->status !== 'available' || $unit->isExpired()) {
                return false;
            }

            $old = ClinicalAuditLog::snapshot($unit);

            $unit->update([
                'status' => 'reserved',
                'current_patient_id' => $patientId,
                'reserved_at' => now(),
            ]);

            ClinicalAuditLog::record($unit, 'reserved', [
                'old' => $old,
                'new' => ClinicalAuditLog::snapshot($unit->fresh()),
            ]);

            return true;
        });
    }

    public function issueUnit(BloodRequest $request, BloodUnit $unit): BloodIssueItem
    {
        return DB::transaction(function () use ($request, $unit) {
            $unit->refresh();

            if ($unit->status === 'issued') {
                throw new \RuntimeException('Blood unit is already issued.');
            }

            $issuedAt = now();
            $userId = MedicalScope::recorderId();

            $issueItem = BloodIssueItem::create([
                'blood_request_id' => $request->getKey(),
                'blood_unit_id' => $unit->getKey(),
                'issued_by' => $userId,
                'issued_at' => $issuedAt,
                'status' => 'issued',
            ]);

            $oldUnit = ClinicalAuditLog::snapshot($unit);
            $unit->update(['status' => 'issued']);

            ClinicalAuditLog::record($unit, 'issued', [
                'old' => $oldUnit,
                'new' => ClinicalAuditLog::snapshot($unit->fresh()),
            ]);

            $oldRequest = ClinicalAuditLog::snapshot($request);
            $request->increment('units_issued');
            $request->refresh();

            if ($request->units_issued >= $request->units_requested) {
                $request->update([
                    'status' => 'fulfilled',
                    'fulfilled_at' => now(),
                ]);
            } elseif ($request->units_issued > 0) {
                $request->update(['status' => 'partially_fulfilled']);
            }

            ClinicalAuditLog::record($request, 'units_issued', [
                'old' => $oldRequest,
                'new' => ClinicalAuditLog::snapshot($request->fresh()),
            ]);

            return $issueItem;
        });
    }

    public function returnUnit(BloodIssueItem $item, string $reason): void
    {
        DB::transaction(function () use ($item, $reason) {
            $item->refresh();

            $oldItem = ClinicalAuditLog::snapshot($item);
            $item->update([
                'status' => 'returned',
                'returned_at' => now(),
                'return_reason' => $reason,
            ]);

            ClinicalAuditLog::record($item, 'returned', [
                'old' => $oldItem,
                'new' => ClinicalAuditLog::snapshot($item->fresh()),
            ]);

            $unit = $item->unit;
            $oldUnit = ClinicalAuditLog::snapshot($unit);
            $unit->update([
                'status' => 'available',
                'current_patient_id' => null,
                'reserved_at' => null,
            ]);

            ClinicalAuditLog::record($unit, 'returned_to_stock', [
                'old' => $oldUnit,
                'new' => ClinicalAuditLog::snapshot($unit->fresh()),
            ]);

            $request = $item->request;
            $oldRequest = ClinicalAuditLog::snapshot($request);
            $request->decrement('units_issued');
            $request->refresh();

            if ($request->units_issued <= 0) {
                $request->update([
                    'status' => 'pending',
                    'fulfilled_at' => null,
                ]);
            } else {
                $request->update(['status' => 'partially_fulfilled']);
            }

            ClinicalAuditLog::record($request, 'units_returned', [
                'old' => $oldRequest,
                'new' => ClinicalAuditLog::snapshot($request->fresh()),
            ]);
        });
    }

    public function markExpired(int $instituteId): int
    {
        $units = BloodUnit::where('institute_id', $instituteId)
            ->whereIn('status', ['available', 'reserved'])
            ->where('expiry_date', '<', now())
            ->get();

        $count = 0;

        foreach ($units as $unit) {
            DB::transaction(function () use ($unit, &$count) {
                $old = ClinicalAuditLog::snapshot($unit);
                $unit->update(['status' => 'expired']);

                ClinicalAuditLog::record($unit, 'expired', [
                    'old' => $old,
                    'new' => ClinicalAuditLog::snapshot($unit->fresh()),
                ]);

                $count++;
            });
        }

        return $count;
    }

    public function discardUnit(BloodUnit $unit, string $reason): void
    {
        DB::transaction(function () use ($unit, $reason) {
            $old = ClinicalAuditLog::snapshot($unit);
            $unit->update([
                'status' => 'discarded',
                'notes' => $reason,
            ]);

            ClinicalAuditLog::record($unit, 'discarded', [
                'old' => $old,
                'new' => ClinicalAuditLog::snapshot($unit->fresh()),
                'reason' => $reason,
            ]);
        });
    }

    public function approveRequest(BloodRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $old = ClinicalAuditLog::snapshot($request);
            $request->update([
                'status' => 'approved',
                'approved_by' => MedicalScope::recorderId(),
                'approved_at' => now(),
            ]);

            ClinicalAuditLog::record($request, 'approved', [
                'old' => $old,
                'new' => ClinicalAuditLog::snapshot($request->fresh()),
            ]);
        });
    }

    public function cancelRequest(BloodRequest $request, string $reason): void
    {
        DB::transaction(function () use ($request, $reason) {
            $reservedUnits = BloodUnit::where('institute_id', $request->institute_id)
                ->where('status', 'reserved')
                ->where('current_patient_id', $request->patient_id)
                ->get();

            foreach ($reservedUnits as $unit) {
                $oldUnit = ClinicalAuditLog::snapshot($unit);
                $unit->update([
                    'status' => 'available',
                    'current_patient_id' => null,
                    'reserved_at' => null,
                ]);

                ClinicalAuditLog::record($unit, 'released_cancelled_request', [
                    'old' => $oldUnit,
                    'new' => ClinicalAuditLog::snapshot($unit->fresh()),
                ]);
            }

            $oldRequest = ClinicalAuditLog::snapshot($request);
            $request->update([
                'status' => 'cancelled',
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ]);

            ClinicalAuditLog::record($request, 'cancelled', [
                'old' => $oldRequest,
                'new' => ClinicalAuditLog::snapshot($request->fresh()),
                'reason' => $reason,
            ]);
        });
    }

    public function expiringSoon(int $instituteId, int $days = 7, ?int $branchId = null)
    {
        $query = BloodUnit::where('institute_id', $instituteId)
            ->where('status', 'available')
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays($days));

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        return $query->with(['donor', 'currentPatient'])
            ->orderBy('expiry_date')
            ->get();
    }

    public function eligibleDonors(int $instituteId, ?int $branchId = null)
    {
        $query = BloodDonor::where('institute_id', $instituteId)
            ->active()
            ->eligible();

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        return $query->orderBy('last_donation_date', 'asc')->get();
    }

    public function lowStock(int $instituteId, int $threshold = 5, ?int $branchId = null): array
    {
        $available = $this->availableByBloodGroup($instituteId, $branchId);

        return array_filter($available, fn ($count) => $count < $threshold);
    }
}
