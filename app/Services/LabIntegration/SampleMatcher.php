<?php

namespace App\Services\LabIntegration;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabSample;
use App\Models\Medical\LabOrder;

class SampleMatcher
{
    /**
     * Strict matching: accession_number first, then barcode, then sample_id.
     * Returns:
     * [
     *   'matched' => bool,
     *   'sample'  => LabSample|null,
     *   'order'   => LabOrder|null,
     *   'reason'  => string|null,
     * ]
     */
    public function match(LabAnalyzer $analyzer, array $parsed): array
    {
        $accession = $parsed['accession_number'] ?? null;
        $barcode = $parsed['sample_barcode'] ?? null;

        if (! $accession && ! $barcode) {
            return ['matched' => false, 'sample' => null, 'order' => null, 'reason' => 'no accession or barcode'];
        }

        // 1. Try accession (strict)
        if ($accession) {
            $sample = LabSample::where('institute_id', $analyzer->institute_id)
                ->where('accession_number', $accession)
                ->first();

            if ($sample) {
                return [
                    'matched' => true,
                    'sample' => $sample,
                    'order' => $sample->labOrder,
                    'reason' => null,
                ];
            }
        }

        // 2. Try barcode
        if ($barcode) {
            $sample = LabSample::where('institute_id', $analyzer->institute_id)
                ->where('barcode', $barcode)
                ->first();

            if ($sample) {
                return [
                    'matched' => true,
                    'sample' => $sample,
                    'order' => $sample->labOrder,
                    'reason' => null,
                ];
            }
        }

        // 3. Try direct lab_order accession
        if ($accession) {
            $order = LabOrder::where('institute_id', $analyzer->institute_id)
                ->where('accession_number', $accession)
                ->first();

            if ($order) {
                return [
                    'matched' => true,
                    'sample' => null,
                    'order' => $order,
                    'reason' => 'order matched via accession; sample record missing',
                ];
            }
        }

        // Not matched → quarantine
        return [
            'matched' => false,
            'sample' => null,
            'order' => null,
            'reason' => "no sample/order found for accession={$accession}, barcode={$barcode}",
        ];
    }
}
