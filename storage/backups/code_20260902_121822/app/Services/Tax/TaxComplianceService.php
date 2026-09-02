<?php

namespace App\Services\Tax;

use App\Models\TaxAuditLog;
use App\Models\TaxJurisdiction;
use App\Models\TaxRate;
use App\Models\TaxRateHistory;
use App\Models\TaxReturnLine;
use App\Models\TaxReturnPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaxComplianceService
{
    public function __construct(
        private readonly TaxEngine $engine,
    ) {}

    public function createJurisdiction(int $instituteId, ?int $branchId, array $data): TaxJurisdiction
    {
        return DB::transaction(function () use ($instituteId, $branchId, $data) {
            return TaxJurisdiction::create(array_merge($data, [
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
            ]));
        });
    }

    public function createRate(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): TaxRate
    {
        return DB::transaction(function () use ($instituteId, $branchId, $data, $actorId) {
            $rate = TaxRate::create(array_merge($data, [
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'created_by' => $actorId,
            ]));

            $this->audit($instituteId, $branchId, 'rate_created', $actorId, 'tax_rate', $rate->id, null, $rate->toArray());

            return $rate;
        });
    }

    public function updateRate(TaxRate $rate, array $data, ?int $actorId = null): TaxRate
    {
        return DB::transaction(function () use ($rate, $data, $actorId) {
            $old = $rate->toArray();
            $oldRate = $rate->rate;

            $rate->update(array_merge($data, ['updated_by' => $actorId]));

            if (isset($data['rate']) && (float) $data['rate'] !== (float) $oldRate) {
                TaxRateHistory::create([
                    'institute_id' => $rate->institute_id,
                    'tax_rate_id' => $rate->id,
                    'old_rate' => $oldRate,
                    'new_rate' => $data['rate'],
                    'changed_at' => now(),
                    'changed_by' => $actorId,
                ]);

                $this->audit($rate->institute_id, $rate->branch_id, 'rate_updated', $actorId, 'tax_rate', $rate->id, $old, $rate->fresh()->toArray());
            }

            return $rate->fresh();
        });
    }

    public function createReturn(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): TaxReturnPeriod
    {
        return DB::transaction(function () use ($instituteId, $branchId, $data, $actorId) {
            $return = TaxReturnPeriod::create(array_merge($data, [
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'created_by' => $actorId,
            ]));

            $this->audit($instituteId, $branchId, 'return_created', $actorId, 'tax_return_period', $return->id, null, $return->toArray());

            return $return;
        });
    }

    public function computeReturn(TaxReturnPeriod $return, int $instituteId, ?int $branchId): TaxReturnPeriod
    {
        return DB::transaction(function () use ($return, $instituteId, $branchId) {
            $rates = TaxRate::query()
                ->where('institute_id', $instituteId)
                ->where('is_active', true)
                ->get();

            $totalSales = 0.0;
            $totalPurchases = 0.0;
            $totalCollected = 0.0;
            $totalPaid = 0.0;

            $return->lines()->delete();

            foreach ($rates as $rate) {
                $lineSales = 0.0;
                $linePurchases = 0.0;
                $lineCollected = 0.0;
                $linePaid = 0.0;

                if ($rate->type === 'vat' || $rate->type === 'sales_tax') {
                    $lineCollected = $totalSales * ($rate->rate / 100);
                    $linePaid = $totalPurchases * ($rate->rate / 100);
                }

                if ($lineSales > 0 || $linePurchases > 0 || $lineCollected > 0 || $linePaid > 0) {
                    TaxReturnLine::create([
                        'institute_id' => $instituteId,
                        'tax_return_id' => $return->id,
                        'tax_rate_id' => $rate->id,
                        'description' => $rate->name,
                        'total_sales' => round($lineSales, 4),
                        'total_purchases' => round($linePurchases, 4),
                        'tax_collected' => round($lineCollected, 4),
                        'tax_paid' => round($linePaid, 4),
                        'net_tax' => round($lineCollected - $linePaid, 4),
                    ]);
                }

                $totalSales += $lineSales;
                $totalPurchases += $linePurchases;
                $totalCollected += $lineCollected;
                $totalPaid += $linePaid;
            }

            $return->update([
                'total_sales' => round($totalSales, 4),
                'total_purchases' => round($totalPurchases, 4),
                'tax_collected' => round($totalCollected, 4),
                'tax_paid' => round($totalPaid, 4),
                'net_tax' => round($totalCollected - $totalPaid, 4),
            ]);

            return $return->fresh('lines');
        });
    }

    public function fileReturn(TaxReturnPeriod $return, ?int $journalId = null, ?int $actorId = null): TaxReturnPeriod
    {
        return DB::transaction(function () use ($return, $journalId, $actorId) {
            if ($return->status !== 'open') {
                throw ValidationException::withMessages(['return' => 'Only open returns can be filed.']);
            }

            $return->update([
                'status' => 'filed',
                'journal_id' => $journalId,
            ]);

            $this->audit($return->institute_id, $return->branch_id, 'return_filed', $actorId, 'tax_return_period', $return->id, ['status' => 'open'], $return->toArray());

            return $return->fresh();
        });
    }

    public function markOverdue(TaxReturnPeriod $return, ?int $actorId = null): TaxReturnPeriod
    {
        return DB::transaction(function () use ($return, $actorId) {
            if ($return->status !== 'open') {
                return $return;
            }

            $return->update(['status' => 'overdue']);

            $this->audit($return->institute_id, $return->branch_id, 'return_overdue', $actorId, 'tax_return_period', $return->id, ['status' => 'open'], ['status' => 'overdue']);

            return $return->fresh();
        });
    }

    public function countryConfig(string $countryIso): ?array
    {
        return config("tax.countries.{$countryIso}");
    }

    public function defaultJurisdictionForInstitute(int $instituteId, ?int $branchId, string $countryIso): ?TaxJurisdiction
    {
        return $this->engine->jurisdictionForCountry($instituteId, $branchId, $countryIso);
    }

    public function ratesSummary(int $instituteId, ?int $branchId, ?string $type = null): array
    {
        $query = TaxRate::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true);

        if ($branchId !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        return $query->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'type' => $r->type,
            'rate' => (float) $r->rate,
            'rate_type' => $r->rate_type,
            'is_inclusive' => $r->is_inclusive,
            'effective_from' => $r->effective_from->toDateString(),
            'effective_to' => $r->effective_to?->toDateString(),
        ])->toArray();
    }

    private function audit(int $instituteId, ?int $branchId, string $event, ?int $actorId, string $entityType, ?int $entityId, mixed $old, mixed $new): void
    {
        if (! config('tax.audit_enabled', true)) {
            return;
        }

        TaxAuditLog::create([
            'institute_id' => $instituteId,
            'branch_id' => $branchId,
            'event' => $event,
            'actor_type' => $actorId ? 'user' : null,
            'actor_id' => $actorId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
