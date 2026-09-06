<?php

namespace App\Services\Medical;

use App\Models\Medical\PharmacyStock;

/**
 * Expiry surveillance: bucketizes in-stock batches into critical (≤7d),
 * warning (8–30d) and expired. Pure function of the ledger — no session or
 * notification side effects (callers decide presentation).
 */
class ExpiryAlertService
{
    /**
     * Buckets of batches needing attention for an institute.
     */
    public function checkAndAlert(int $instituteId): array
    {
        $alerts = [
            'critical' => [],
            'warning' => [],
            'info' => [],
        ];

        // Critical: expiring within 7 days.
        $critical = PharmacyStock::where('institute_id', $instituteId)
            ->where('current_quantity', '>', 0)
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays(7))
            ->with('medicine')
            ->orderBy('expiry_date')
            ->get();

        foreach ($critical as $item) {
            $alerts['critical'][] = [
                'medicine' => $item->medicine,
                'batch' => $item->batch_number,
                'quantity' => $item->current_quantity,
                'expiry_date' => $item->expiry_date,
                'days_left' => now()->diffInDays($item->expiry_date),
            ];
        }

        // Warning: expiring within 8–30 days.
        $warning = PharmacyStock::where('institute_id', $instituteId)
            ->where('current_quantity', '>', 0)
            ->where('expiry_date', '>', now()->addDays(7))
            ->where('expiry_date', '<=', now()->addDays(30))
            ->with('medicine')
            ->orderBy('expiry_date')
            ->get();

        foreach ($warning as $item) {
            $alerts['warning'][] = [
                'medicine' => $item->medicine,
                'batch' => $item->batch_number,
                'quantity' => $item->current_quantity,
                'expiry_date' => $item->expiry_date,
                'days_left' => now()->diffInDays($item->expiry_date),
            ];
        }

        // Info: already expired but still holding quantity.
        $expired = PharmacyStock::where('institute_id', $instituteId)
            ->where('current_quantity', '>', 0)
            ->where('expiry_date', '<=', now())
            ->with('medicine')
            ->orderBy('expiry_date')
            ->get();

        foreach ($expired as $item) {
            $alerts['info'][] = [
                'medicine' => $item->medicine,
                'batch' => $item->batch_number,
                'quantity' => $item->current_quantity,
                'expiry_date' => $item->expiry_date,
                'days_overdue' => now()->diffInDays($item->expiry_date),
            ];
        }

        return $alerts;
    }

    /**
     * Alert summary counts for dashboards.
     */
    public function getAlertSummary(int $instituteId): array
    {
        $alerts = $this->checkAndAlert($instituteId);

        return [
            'critical_count' => count($alerts['critical']),
            'warning_count' => count($alerts['warning']),
            'expired_count' => count($alerts['info']),
            'total' => count($alerts['critical']) + count($alerts['warning']) + count($alerts['info']),
        ];
    }
}
