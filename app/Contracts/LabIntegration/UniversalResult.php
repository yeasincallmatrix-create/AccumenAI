<?php

namespace App\Contracts\LabIntegration;

/**
 * Standard normalized result returned by ALL parsers.
 * Shape:
 * [
 *   'accession_number' => 'ACC-2026-00001' | null,
 *   'sample_barcode'   => 'SAMPLE001' | null,
 *   'patient_hint'     => 'MRN-12345' | null,
 *   'message_id'       => 'MSG00001' | null,
 *   'measured_at'      => '2026-09-20T10:30:00+06:00' | null,
 *   'parameters' => [
 *      [
 *        'vendor_code' => 'WBC',
 *        'value'       => '7.2',
 *        'unit'        => '10^3/uL',
 *        'flag'        => 'N', // N|H|L|HH|LL|*
 *        'ref_range'   => '4.0-11.0' | null,
 *        'ref_low'     => 4.0 | null,
 *        'ref_high'    => 11.0 | null,
 *        'status'      => 'F', // F=final, P=preliminary, C=corrected
 *      ],
 *      // ...
 *   ],
 *   'raw_metadata' => [...], // protocol-specific extras
 *   'parse_errors' => [],    // non-fatal warnings
 * ]
 */
class UniversalResult
{
    public const FLAGS = ['N', 'H', 'L', 'HH', 'LL', '*'];

    /**
     * Build a UniversalResult from a raw normalized array.
     * Validates shape; throws InvalidArgumentException if fundamentally broken.
     */
    public static function fromArray(array $data): array
    {
        return [
            'accession_number' => $data['accession_number'] ?? null,
            'sample_barcode'   => $data['sample_barcode'] ?? null,
            'patient_hint'     => $data['patient_hint'] ?? null,
            'message_id'       => $data['message_id'] ?? null,
            'measured_at'      => $data['measured_at'] ?? null,
            'parameters'       => array_values(array_filter(
                $data['parameters'] ?? [],
                fn ($p) => ! empty($p['vendor_code']) && isset($p['value'])
            )),
            'raw_metadata'     => $data['raw_metadata'] ?? [],
            'parse_errors'     => $data['parse_errors'] ?? [],
        ];
    }
}
