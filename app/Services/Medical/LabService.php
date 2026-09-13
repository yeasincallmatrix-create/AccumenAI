<?php

namespace App\Services\Medical;

use App\Models\Institute;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabResult;
use App\Models\Medical\NumberSequence;
use App\Support\MedicalScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Lab order pipeline: numbering, creation with result stubs, sample
 * collection, result entry with range interpretation, PDF reporting.
 */
class LabService
{
    /**
     * Generate a unique order number (stored LAB-YYYY-NNNNN, displayed
     * LAB-YY-NNNNN) via the database-backed sequence (Phase 04).
     */
    public function generateOrderNumber(int $instituteId): string
    {
        return app(NumberSequenceService::class)->next(NumberSequence::TYPE_LAB_ORDER, $instituteId);
    }

    /**
     * Create a new lab order with one pending result row per test.
     */
    public function createOrder(array $data, array $tests): LabOrder
    {
        return DB::transaction(function () use ($data, $tests) {
            $instituteId = MedicalScope::instituteIdOrFail();
            $data['institute_id'] = $instituteId;
            $data['order_number'] = $this->generateOrderNumber($instituteId);
            $data['order_date'] = $data['order_date'] ?? now()->format('Y-m-d');
            $data['status'] = 'ordered';

            $order = LabOrder::create($data);

            foreach ($tests as $test) {
                LabResult::create([
                    'lab_order_id' => $order->id,
                    'lab_test_id' => $test['lab_test_id'],
                    'normal_range' => $test['normal_range'] ?? null,
                    'status' => 'pending',
                ]);
            }

            return $order->load('results.labTest');
        });
    }

    /**
     * Mark order as collected.
     */
    public function collectSample(LabOrder $order): void
    {
        $order->update([
            'status' => 'collected',
            'collected_at' => now(),
            'collected_by' => MedicalScope::recorderId(),
        ]);
    }

    /**
     * Enter results for an order (rows scoped to the order itself).
     */
    public function enterResults(LabOrder $order, array $results): void
    {
        DB::transaction(function () use ($order, $results) {
            foreach ($results as $resultId => $data) {
                $result = $order->results()->findOrFail($resultId);
                $result->update([
                    'result_value' => $data['result_value'] ?? null,
                    'result_text' => $data['result_text'] ?? null,
                    'status' => $this->determineResultStatus($result, $data),
                    'comments' => $data['comments'] ?? null,
                ]);
            }

            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => MedicalScope::recorderId(),
            ]);
        });
    }

    /**
     * Determine result status (normal/abnormal/critical).
     */
    private function determineResultStatus(LabResult $result, array $data): string
    {
        if (empty($data['result_value']) && empty($data['result_text'])) {
            return 'pending';
        }

        if (! is_numeric($data['result_value'] ?? null)) {
            // Qualitative result (Positive/Negative, text) — flag against
            // the reference text when present, else leave for human review.
            return 'pending';
        }

        $normalRange = $result->normal_range
            ?? $result->labTest->normal_range
            ?? null;

        if (! $normalRange) {
            return 'normal';
        }

        // Parse normal range (e.g. "4.0-11.0", "< 5.7", "> 70").
        $value = (float) $data['result_value'];

        // Check for critical values (by configured thresholds).
        if ($this->isCritical($value, $result->labTest)) {
            return 'critical';
        }

        // Check if value is within normal range.
        if ($this->isWithinNormalRange($value, $normalRange)) {
            return 'normal';
        }

        return 'abnormal';
    }

    /**
     * Check if value is within normal range.
     */
    private function isWithinNormalRange(float $value, string $range): bool
    {
        // Handle "< 5.7" format (upper bound).
        if (preg_match('/<\s*([\d.]+)/', $range, $matches)) {
            return $value < (float) $matches[1];
        }

        // Handle "> 70" format (lower bound).
        if (preg_match('/>\s*([\d.]+)/', $range, $matches)) {
            return $value > (float) $matches[1];
        }

        // Handle "4.0-11.0" format.
        if (preg_match('/([\d.]+)\s*-\s*([\d.]+)/', $range, $matches)) {
            return $value >= (float) $matches[1] && $value <= (float) $matches[2];
        }

        // Default: if no pattern matches, assume normal.
        return true;
    }

    /**
     * Check if value is critical.
     */
    private function isCritical(float $value, $test): bool
    {
        // Heuristic thresholds by test-name keyword (a proper critical
        // range table is Phase 5+ scope).
        $criticalRanges = [
            'glucose' => ['min' => 30, 'max' => 500],
            'potassium' => ['min' => 2.0, 'max' => 7.0],
            'sodium' => ['min' => 110, 'max' => 170],
            'hemoglobin' => ['min' => 4, 'max' => 25],
            'platelet' => ['min' => 10, 'max' => 1500],
            'spo2' => ['min' => 70, 'max' => 100],
        ];

        $testName = strtolower($test->name ?? '');
        foreach ($criticalRanges as $key => $range) {
            if (strpos($testName, $key) !== false) {
                return $value < $range['min'] || $value > $range['max'];
            }
        }

        return false;
    }

    /**
     * Generate lab report PDF.
     */
    public function generateReport(LabOrder $order): \Barryvdh\DomPDF\PDF
    {
        $order->load(['patient', 'doctor', 'results.labTest']);

        $institute = Institute::find($order->institute_id);

        $data = [
            'order' => $order,
            'patient' => $order->patient,
            'doctor' => $order->doctor,
            'results' => $order->results,
            'hospital_name' => $institute->name ?? 'Hospital',
            'hospital_address' => $institute->address ?? '',
            'generated_at' => now()->format('d M Y h:i A'),
        ];

        return Pdf::loadView('medical.lab.reports.show', $data);
    }

    /**
     * Get pending orders for lab dashboard.
     */
    public function getPendingOrders(int $instituteId): array
    {
        $orders = LabOrder::where('institute_id', $instituteId)
            ->whereIn('status', ['ordered', 'collected', 'processing'])
            ->with(['patient', 'doctor'])
            ->orderBy('order_date')
            ->get();

        return [
            'total' => $orders->count(),
            'ordered' => $orders->where('status', 'ordered')->count(),
            'collected' => $orders->where('status', 'collected')->count(),
            'processing' => $orders->where('status', 'processing')->count(),
            'list' => $orders,
        ];
    }
}
