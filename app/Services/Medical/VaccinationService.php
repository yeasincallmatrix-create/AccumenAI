<?php

namespace App\Services\Medical;

use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\VaccineMaster;
use App\Models\Medical\VaccinationSchedule;
use App\Models\Medical\VaccinationRecord;
use App\Models\Medical\VaccineStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VaccinationService
{
    public function generateEpiSchedule(int $instituteId, int $patientId, \DateTime $birthDate): array
    {
        $epiVaccines = VaccineMaster::where(function ($q) use ($instituteId) {
            $q->whereNull('institute_id')->orWhere('institute_id', $instituteId);
        })->where('category', 'EPI')->where('is_active', true)->get();

        $created = [];
        foreach ($epiVaccines as $vaccine) {
            for ($dose = 1; $dose <= $vaccine->doses_in_series; $dose++) {
                $dueDays = $vaccine->min_age_days + (($dose - 1) * ($vaccine->interval_days_min ?? 30));
                $dueDate = (clone $birthDate)->modify("+{$dueDays} days");

                $created[] = VaccinationSchedule::firstOrCreate(
                    [
                        'institute_id' => $instituteId,
                        'patient_id' => $patientId,
                        'vaccine_master_id' => $vaccine->id,
                        'dose_number' => $dose,
                    ],
                    [
                        'due_date' => $dueDate,
                        'age_in_days_at_due' => $dueDays,
                        'status' => 'scheduled',
                    ]
                );
            }
        }
        return $created;
    }

    public function getDueToday(int $instituteId, ?int $branchId = null): Collection
    {
        $query = VaccinationSchedule::forInstitute($instituteId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'scheduled')
            ->whereDate('due_date', '<=', today())
            ->with(['patient', 'vaccineMaster']);
        return $query->orderBy('due_date')->get();
    }

    public function getOverdue(int $instituteId, ?int $branchId = null): Collection
    {
        $query = VaccinationSchedule::forInstitute($instituteId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'scheduled')
            ->whereDate('due_date', '<', today())
            ->with(['patient', 'vaccineMaster']);
        return $query->orderBy('due_date')->get();
    }

    public function recordVaccination(VaccinationSchedule $schedule, array $data, int $userId): VaccinationRecord
    {
        return DB::transaction(function () use ($schedule, $data, $userId) {
            $old = ClinicalAuditLog::snapshot($schedule);

            $seqService = app(\App\Services\Medical\NumberSequenceService::class);
            $recordNumber = $seqService->next(NumberSequence::TYPE_VACCINATION_RECORD, $schedule->institute_id);

            $record = VaccinationRecord::create([
                'institute_id' => $schedule->institute_id,
                'branch_id' => $schedule->branch_id,
                'record_number' => $recordNumber,
                'patient_id' => $schedule->patient_id,
                'vaccine_master_id' => $schedule->vaccine_master_id,
                'vaccination_schedule_id' => $schedule->id,
                'dose_number' => $schedule->dose_number,
                'administered_date' => $data['administered_date'] ?? today(),
                'administered_at' => now(),
                'administered_by' => $userId,
                'site' => $data['site'] ?? null,
                'route' => $data['route'] ?? null,
                'dose_volume' => $data['dose_volume'] ?? null,
                'batch_number' => $data['batch_number'] ?? null,
                'batch_expiry' => $data['batch_expiry'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'pre_vaccination_notes' => $data['pre_vaccination_notes'] ?? null,
                'post_vaccination_notes' => $data['post_vaccination_notes'] ?? null,
                'adverse_event' => $data['adverse_event'] ?? 'none',
                'adverse_event_details' => $data['adverse_event_details'] ?? null,
                'fee' => $data['fee'] ?? 0,
                'payment_status' => 'pending',
            ]);

            $schedule->update([
                'status' => 'given',
                'given_date' => $record->administered_date,
            ]);

            $this->generateCertificate($record);

            [$oldVals, $newVals] = ClinicalAuditLog::diff($old, ClinicalAuditLog::snapshot($schedule->fresh()));
            if ($oldVals || $newVals) {
                ClinicalAuditLog::record($schedule, 'vaccinated', ['old' => $oldVals, 'new' => $newVals]);
            }

            if (! empty($data['batch_number'])) {
                $stock = VaccineStock::where('institute_id', $schedule->institute_id)
                    ->where('vaccine_master_id', $schedule->vaccine_master_id)
                    ->where('batch_number', $data['batch_number'])
                    ->first();
                if ($stock && $stock->quantity_available > 0) {
                    $stock->decrement('quantity_available');
                    $stock->increment('quantity_used');
                }
            }

            $master = $schedule->vaccineMaster;
            if ($schedule->dose_number < $master->doses_in_series && $master->interval_days_min) {
                $nextDose = $schedule->dose_number + 1;
                $nextDue = $record->administered_date->copy()->addDays($master->interval_days_min);
                $record->update(['next_dose_due' => $nextDue]);

                VaccinationSchedule::firstOrCreate(
                    [
                        'institute_id' => $schedule->institute_id,
                        'patient_id' => $schedule->patient_id,
                        'vaccine_master_id' => $master->id,
                        'dose_number' => $nextDose,
                    ],
                    [
                        'due_date' => $nextDue,
                        'status' => 'scheduled',
                    ]
                );
            }

            return $record;
        });
    }

    public function generateCertificate(VaccinationRecord $record): string
    {
        $certNumber = 'VC-' . $record->institute_id . '-' . str_pad($record->id, 6, '0', STR_PAD_LEFT);
        $record->update([
            'certificate_number' => $certNumber,
            'certificate_issued_at' => now(),
        ]);
        return $certNumber;
    }

    public function expiringStocks(int $instituteId, ?int $branchId = null, int $days = 30): Collection
    {
        $query = VaccineStock::where('institute_id', $instituteId)
            ->where('status', 'available')
            ->where('expiry_date', '<=', today()->addDays($days))
            ->where('expiry_date', '>=', today())
            ->with(['vaccineMaster']);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        return $query->orderBy('expiry_date')->get();
    }

    public function lowStockCount(int $instituteId, ?int $branchId = null): int
    {
        $query = VaccineStock::where('institute_id', $instituteId)
            ->where('status', 'available')
            ->where('quantity_available', '<=', 5)
            ->where('quantity_available', '>', 0);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        return $query->count();
    }
}
