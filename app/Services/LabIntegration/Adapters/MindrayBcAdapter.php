<?php

namespace App\Services\LabIntegration\Adapters;

use App\Models\LabIntegration\LabAnalyzer;
use App\Services\LabIntegration\ParameterMapper;
use App\Services\LabIntegration\Parsers\AstmParser;
use App\Services\LabIntegration\Parsers\Hl7Parser;
use App\Services\LabIntegration\UnitConverter;

class MindrayBcAdapter extends VendorAdapterBase
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
        return 'mindray_bc';
    }

    public function version(): string
    {
        return 'v1';
    }

    public function protocol(): string
    {
        return 'astm';
    }

    public function capabilities(): array
    {
        return [
            'result_upload' => true,
            'worklist' => false, // BC-5150 base firmware does not support worklist
            'query' => false,
            'bidirectional' => false, // result upload only
        ];
    }

    public function matchesVendor(string $handshakePayload): bool
    {
        // Mindray sends MINDRAY / BC-5150 / BC5150 in H-record/manufacturer field
        return (bool) preg_match('/\bMINDRAY\b|\bBC[- ]?5150\b|\bBC5150\b/i', $handshakePayload);
    }

    /**
     * Parse BC-5150 payload. Auto-detects ASTM vs HL7.
     * Wraps Phase 2 parsers (no duplicate parsing); unreachable protocols
     * degrade to error-state arrays, never exceptions.
     */
    public function parse(string $rawPayload, LabAnalyzer $analyzer): array
    {
        try {
            $preprocessed = $this->preprocess($rawPayload, $analyzer);
        } catch (\Throwable $e) {
            return $this->errorState($analyzer, 'preprocess failed: '.$e->getMessage());
        }

        $protocol = $this->detectProtocol($preprocessed);

        try {
            $parsed = match ($protocol) {
                'hl7' => $this->hl7->parse($preprocessed),
                'astm' => $this->astm->parse($preprocessed),
            };
        } catch (\Throwable $e) {
            return $this->errorState($analyzer, 'parser failed: '.$e->getMessage(), $protocol);
        }

        $parsed = $this->postprocess($parsed);
        $parsed = $this->enrichWithMappings($parsed, $analyzer);
        $parsed['raw_metadata']['adapter'] = $this->key().':'.$this->version();
        $parsed['raw_metadata']['protocol'] = $protocol;

        return $parsed;
    }

    /**
     * Mindray quirks:
     *  1. Line endings vary (\r\n vs \n) — normalize
     *  2. "N/A" and "--" placeholder values — treat as empty
     *  3. MID% / GRAN% / LYM% vendor codes differ from Sysmex NEUT%/MONO%/LYMPH%
     */
    protected function preprocess(string $raw, LabAnalyzer $analyzer): string
    {
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
        if (preg_match('/^MSH\|/', ltrim($raw))) {
            return 'hl7';
        }
        if (preg_match('/^H\|/', ltrim($raw))) {
            return 'astm';
        }

        return 'astm';
    }

    /**
     * Post-process:
     *  - "N/A" / "--" / empty values → skip
     *  - Asterisk error values → parse error
     *  - Trim values
     */
    protected function postprocess(array $parsed): array
    {
        $errors = $parsed['parse_errors'] ?? [];

        $parsed['parameters'] = array_values(array_filter(
            array_map(function ($p) use (&$errors) {
                if (! isset($p['value'])) {
                    return $p;
                }

                $value = trim((string) $p['value']);

                // Placeholder values
                if (in_array(strtoupper($value), ['N/A', '--', 'NA', ''], true)) {
                    return null;
                }

                // Asterisk errors
                if (preg_match('/^\*+$/', $value)) {
                    $errors[] = "Analyzer error for {$p['vendor_code']}: asterisk value";

                    return null;
                }

                $p['value'] = $value;

                return $p;
            }, $parsed['parameters'] ?? []),
            fn ($p) => $p !== null
        ));

        $parsed['parse_errors'] = $errors;

        return $parsed;
    }

    public function buildWorklist(array $orderData, LabAnalyzer $analyzer): ?string
    {
        // BC-5150 base firmware does not support worklist
        return null;
    }

    protected function errorState(LabAnalyzer $analyzer, string $error, ?string $protocol = null): array
    {
        return [
            'accession_number' => null,
            'sample_barcode' => null,
            'patient_hint' => null,
            'message_id' => null,
            'measured_at' => null,
            'parameters' => [],
            'raw_metadata' => array_filter([
                'adapter' => $this->key().':'.$this->version(),
                'protocol' => $protocol,
            ]),
            'parse_errors' => [$error],
        ];
    }
}
