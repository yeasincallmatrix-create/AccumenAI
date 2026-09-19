<?php

namespace App\Services\LabIntegration\Adapters;

use App\Models\LabIntegration\LabAnalyzer;
use App\Services\LabIntegration\ParameterMapper;
use App\Services\LabIntegration\Parsers\AstmParser;
use App\Services\LabIntegration\Parsers\Hl7Parser;
use App\Services\LabIntegration\UnitConverter;

class SysmexXnAdapter extends VendorAdapterBase
{
    public function __construct(
        protected AstmParser $astm,
        protected Hl7Parser $hl7,
        ParameterMapper $mapper,
        UnitConverter $converter,
    ) {
        parent::__construct($mapper, $converter);
    }

    public function key(): string
    {
        return 'sysmex_xn';
    }

    public function version(): string
    {
        return 'v1';
    }

    public function protocol(): string
    {
        return 'astm'; // XN-550 default; HL7 supported and auto-detected
    }

    public function capabilities(): array
    {
        return [
            'result_upload' => true,
            'worklist' => true,   // XN-550 supports worklist
            'query' => false,  // no query interface
            'bidirectional' => true,   // both directions supported
        ];
    }

    public function matchesVendor(string $handshakePayload): bool
    {
        // Sysmex sends "SYSMEX" or "XN" in H-record/manufacturer field
        return (bool) preg_match('/\bSYSMEX\b|\bXN[- ]?\d{3,4}\b/i', $handshakePayload);
    }

    /**
     * Parse Sysmex XN-550 payload. Auto-detects ASTM vs HL7.
     * Pre-processes Sysmex-specific quirks, then delegates to Phase 2 parsers.
     */
    public function parse(string $rawPayload, LabAnalyzer $analyzer): array
    {
        try {
            $preprocessed = $this->preprocess($rawPayload, $analyzer);
        } catch (\Throwable $e) {
            return [
                'accession_number' => null,
                'sample_barcode' => null,
                'patient_hint' => null,
                'message_id' => null,
                'measured_at' => null,
                'parameters' => [],
                'raw_metadata' => ['adapter' => $this->key().':'.$this->version()],
                'parse_errors' => ['preprocess failed: '.$e->getMessage()],
            ];
        }

        // Detect protocol
        $protocol = $this->detectProtocol($preprocessed);

        // Delegate to Phase 2 parser
        try {
            $parsed = match ($protocol) {
                'hl7' => $this->hl7->parse($preprocessed),
                'astm' => $this->astm->parse($preprocessed),
            };
        } catch (\Throwable $e) {
            return [
                'accession_number' => null,
                'sample_barcode' => null,
                'patient_hint' => null,
                'message_id' => null,
                'measured_at' => null,
                'parameters' => [],
                'raw_metadata' => ['adapter' => $this->key().':'.$this->version(), 'protocol' => $protocol],
                'parse_errors' => ['parser failed: '.$e->getMessage()],
            ];
        }

        // Apply XN-550-specific post-processing
        $parsed = $this->postprocess($parsed);

        // Enrich with vendor parameter maps + unit conversion
        $parsed = $this->enrichWithMappings($parsed, $analyzer);
        $parsed['raw_metadata']['adapter'] = $this->key().':'.$this->version();
        $parsed['raw_metadata']['protocol'] = $protocol;

        return $parsed;
    }

    /**
     * Sysmex quirks:
     *  1. Some firmware uses \r\n, some \r — normalize to \n (Phase 2 parsers handle)
     *  2. Blank parameters may appear with empty values — parser skips them already
     *  3. Analyzer name in H-record field 4 may include spaces
     *  4. Some firmware wraps short lines — no action needed
     */
    protected function preprocess(string $raw, LabAnalyzer $analyzer): string
    {
        // Normalize line endings (Phase 2 parsers also do this; belt-and-suspenders)
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        // Strip leading/trailing whitespace lines
        $lines = array_filter(
            array_map('rtrim', explode("\n", $raw)),
            fn ($l) => trim($l) !== ''
        );

        return implode("\n", $lines);
    }

    protected function detectProtocol(string $raw): string
    {
        // HL7 starts with MSH|
        if (preg_match('/^MSH\|/', ltrim($raw))) {
            return 'hl7';
        }
        // ASTM starts with H|
        if (preg_match('/^H\|/', ltrim($raw))) {
            return 'astm';
        }

        // Default to ASTM (Sysmex out-of-box)
        return 'astm';
    }

    /**
     * Post-process parsed result:
     *  - Sysmex RDW-SD sometimes sent with unit "fL" but canonical is "fL" — no change
     *  - Sysmex uses "NEUT" but universal is "NEU_PCT" — handled by parameter maps
     *  - Some values come as "****" (error) — flag as parse error
     */
    protected function postprocess(array $parsed): array
    {
        $errors = $parsed['parse_errors'] ?? [];

        $parsed['parameters'] = array_values(array_filter(
            array_map(function ($p) use (&$errors) {
                // Handle "****" error values
                if (isset($p['value']) && preg_match('/^\*+$/', (string) $p['value'])) {
                    $errors[] = "Analyzer error for {$p['vendor_code']}: asterisk value";

                    return null;
                }
                // Strip whitespace from value
                if (isset($p['value'])) {
                    $p['value'] = trim((string) $p['value']);
                }

                return $p;
            }, $parsed['parameters'] ?? []),
            fn ($p) => $p !== null
        ));

        $parsed['parse_errors'] = $errors;

        return $parsed;
    }

    public function buildWorklist(array $orderData, LabAnalyzer $analyzer): ?string
    {
        // Basic HL7 ORM^O01 worklist message builder for XN-550
        // (Full bidirectional support comes in Phase 8; this is scaffold)
        $msgId = 'WL'.now()->format('YmdHis');
        $accession = $orderData['accession_number'] ?? 'UNKNOWN';
        $patient = $orderData['patient_name'] ?? 'UNKNOWN';
        $patientId = $orderData['patient_id'] ?? 'UNKNOWN';

        $segments = [
            'MSH|^~\\&|HIS|HOSPITAL|SYSMEX XN-550|LAB|'.now()->format('YmdHis')."||ORM^O01|{$msgId}|P|2.5",
            "PID|1||{$patientId}||{$patient}",
            "ORC|NW|{$accession}",
            "OBR|1|{$accession}||CBC^Complete Blood Count",
        ];

        return implode("\r", $segments)."\r";
    }
}
