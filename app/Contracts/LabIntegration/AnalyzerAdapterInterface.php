<?php

namespace App\Contracts\LabIntegration;

use App\Models\LabIntegration\LabAnalyzer;

interface AnalyzerAdapterInterface
{
    /**
     * Unique adapter key (e.g., 'sysmex_xn', 'mindray_bc').
     */
    public function key(): string;

    /**
     * Adapter version (e.g., 'v1', 'v2').
     */
    public function version(): string;

    /**
     * Protocol handled: astm, hl7, vendor, file, csv.
     */
    public function protocol(): string;

    /**
     * Capabilities array: ['result_upload' => bool, 'worklist' => bool, 'query' => bool, 'bidirectional' => bool].
     */
    public function capabilities(): array;

    /**
     * Parse raw payload into a normalized array.
     * Return shape: [
     *   'accession_number' => string|null,
     *   'sample_barcode' => string|null,
     *   'patient_id_hint' => string|null,
     *   'parameters' => [
     *     ['vendor_code' => 'WBC', 'value' => '7.2', 'unit' => '10^3/uL', 'flag' => 'N', 'ref_range' => '4.0-11.0'],
     *     ...
     *   ],
     *   'raw_metadata' => [...],
     * ]
     */
    public function parse(string $rawPayload, LabAnalyzer $analyzer): array;

    /**
     * Optional: build a worklist message to send to the analyzer (bidirectional).
     * Return null if not supported.
     */
    public function buildWorklist(array $orderData, LabAnalyzer $analyzer): ?string;

    /**
     * Optional: check if an incoming handshake/hello payload matches this vendor.
     */
    public function matchesVendor(string $handshakePayload): bool;
}
