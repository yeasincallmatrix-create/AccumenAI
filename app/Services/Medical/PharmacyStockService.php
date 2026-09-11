<?php

namespace App\Services\Medical;

use App\Models\Medical\Medicine;
use App\Models\Medical\PharmacyStock;
use App\Support\MedicalScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pharmacy stock ledger: batch intake, FEFO deduction, adjustments and
 * expiry/low-stock reporting. All queries institute-scoped.
 */
class PharmacyStockService
{
    /**
     * Add stock for a medicine (new batch).
     */
    public function addStock(array $data): PharmacyStock
    {
        $instituteId = MedicalScope::instituteIdOrFail();
        $data['institute_id'] = $instituteId;

        // Check if batch already exists (scoped — the DB unique key is
        // global on (medicine_id, batch_number), so check globally too).
        $existing = PharmacyStock::where('medicine_id', $data['medicine_id'])
            ->where('batch_number', $data['batch_number'])
            ->first();

        if ($existing) {
            throw new \RuntimeException('Batch number already exists for this medicine.');
        }

        // Medicine must belong to this institute.
        Medicine::where('institute_id', $instituteId)->findOrFail($data['medicine_id']);

        return DB::transaction(function () use ($data) {
            return PharmacyStock::create($data);
        });
    }

    /**
     * Deduct stock (dispense).
     */
    public function deductStock(int $instituteId, int $stockId, int $quantity): bool
    {
        return DB::transaction(function () use ($instituteId, $stockId, $quantity) {
            $stock = PharmacyStock::where('institute_id', $instituteId)
                ->lockForUpdate()
                ->findOrFail($stockId);

            if ($stock->expiry_date < now()) {
                throw new \RuntimeException('This batch has expired.');
            }

            if ($stock->current_quantity < $quantity) {
                throw new \RuntimeException('Insufficient stock. Available: '.$stock->current_quantity);
            }

            $stock->decrement('current_quantity', $quantity);

            return true;
        });
    }

    /**
     * Constrain a stock query to a branch (own branch + legacy NULLs).
     * Null branch = institute-wide (existing behavior preserved).
     */
    private function scopeBranch($query, ?int $branchId)
    {
        if ($branchId === null) {
            return $query;
        }

        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
    }

    /**
     * Get available (unexpired, positive) stock for a medicine.
     * Phase 18.1: optional branch limitation applied in SQL.
     */
    public function getAvailableStock(int $instituteId, int $medicineId, ?int $branchId = null): int
    {
        return (int) $this->scopeBranch(
            PharmacyStock::where('institute_id', $instituteId)
                ->where('medicine_id', $medicineId)
                ->where('current_quantity', '>', 0)
                ->where('expiry_date', '>', now()),
            $branchId
        )->sum('current_quantity');
    }

    /**
     * Split a dispense quantity across FEFO batches.
     *
     * Phase 18.1: optional branch limitation applied in SQL (FEFO expiry
     * ordering preserved). No reservation table exists in this
     * architecture; the row-locked decrement in deductStock remains the
     * concurrency primitive (documented limitation).
     *
     * @return array{stock_id:int,batch_number:string,quantity:int,expiry_date:mixed}[]
     */
    public function getStockBatches(int $instituteId, int $medicineId, int $quantity, ?int $branchId = null): array
    {
        $batches = $this->scopeBranch(
            PharmacyStock::where('institute_id', $instituteId)
                ->where('medicine_id', $medicineId)
                ->where('current_quantity', '>', 0)
                ->where('expiry_date', '>', now())
                ->orderBy('expiry_date')
                ->lockForUpdate(),
            $branchId
        )->get();

        $result = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min($batch->current_quantity, $remaining);
            $result[] = [
                'stock_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'quantity' => $deduct,
                'expiry_date' => $batch->expiry_date,
            ];
            $remaining -= $deduct;
        }

        if ($remaining > 0) {
            throw new \RuntimeException('Insufficient stock. Short by '.$remaining.' units.');
        }

        return $result;
    }

    /**
     * Get near-expiry stock. Phase 18.1: optional branch limitation in SQL.
     */
    public function getNearExpiryStock(int $instituteId, int $days = 30, ?int $branchId = null): Collection
    {
        return $this->scopeBranch(
            PharmacyStock::where('institute_id', $instituteId)
                ->where('current_quantity', '>', 0)
                ->where('expiry_date', '>', now())
                ->where('expiry_date', '<=', now()->addDays($days))
                ->with('medicine')
                ->orderBy('expiry_date'),
            $branchId
        )->get();
    }

    /**
     * Get expired stock (still holding quantity).
     * Phase 18.1: optional branch limitation in SQL.
     */
    public function getExpiredStock(int $instituteId, ?int $branchId = null): Collection
    {
        return $this->scopeBranch(
            PharmacyStock::where('institute_id', $instituteId)
                ->where('current_quantity', '>', 0)
                ->where('expiry_date', '<=', now())
                ->with('medicine')
                ->orderBy('expiry_date'),
            $branchId
        )->get();
    }

    /**
     * Get medicines at/below their reorder level.
     *
     * Returns a base Support collection of stdClass rows (medicine,
     * available_stock, reorder_level, needs_reorder) — not Eloquent models.
     */
    public function getLowStockItems(int $instituteId, ?int $branchId = null): \Illuminate\Support\Collection
    {
        $medicines = Medicine::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->get();

        $lowStock = [];

        foreach ($medicines as $medicine) {
            $available = $this->getAvailableStock($instituteId, $medicine->id, $branchId);
            if ($available <= $medicine->reorder_level) {
                $lowStock[] = (object) [
                    'medicine' => $medicine,
                    'available_stock' => $available,
                    'reorder_level' => $medicine->reorder_level,
                    'needs_reorder' => true,
                ];
            }
        }

        return collect($lowStock);
    }

    /**
     * Adjust stock (physical count, damaged, etc.).
     */
    public function adjustStock(int $instituteId, int $stockId, int $newQuantity, string $reason): bool
    {
        return DB::transaction(function () use ($instituteId, $stockId, $newQuantity, $reason) {
            $stock = PharmacyStock::where('institute_id', $instituteId)
                ->lockForUpdate()
                ->findOrFail($stockId);

            if ($newQuantity < 0) {
                throw new \RuntimeException('Quantity cannot be negative.');
            }

            if ($stock->expiry_date < now() && $newQuantity > 0) {
                throw new \RuntimeException('Cannot add stock to an expired batch.');
            }

            $stock->update([
                'current_quantity' => $newQuantity,
                'notes' => ($stock->notes ? $stock->notes."\n" : '').
                    "Adjustment to {$newQuantity} ({$reason})",
            ]);

            return true;
        });
    }
}
