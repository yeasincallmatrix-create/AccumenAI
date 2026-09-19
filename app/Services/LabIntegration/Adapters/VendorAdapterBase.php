<?php

namespace App\Services\LabIntegration\Adapters;

use App\Contracts\LabIntegration\AnalyzerAdapterInterface;
use App\Models\LabIntegration\LabAnalyzer;
use App\Services\LabIntegration\ParameterMapper;
use App\Services\LabIntegration\UnitConverter;

abstract class VendorAdapterBase implements AnalyzerAdapterInterface
{
    public function __construct(
        protected ParameterMapper $mapper,
        protected UnitConverter $converter,
    ) {}

    /**
     * Enrich a UniversalResult (from Phase 2 parser) with
     * universal_code + unit conversion + lab_test_id, using the
     * analyzer's parameter maps.
     *
     * Called by every vendor adapter after delegating to a Phase 2 parser.
     */
    protected function enrichWithMappings(array $parsed, LabAnalyzer $analyzer): array
    {
        $parsed['parameters'] = array_map(function ($param) use ($analyzer) {
            $mapping = $this->mapper->resolve($analyzer, $param['vendor_code']);

            // Unit conversion
            $conversion = $this->converter->convert(
                $param['value'],
                $param['unit'] ?? null,
                $mapping['unit_to'] ?? null,
                $mapping['conversion_factor'] ?? null
            );

            // Merge: mapping provides universal_code, lab_test_id, ref ranges
            // Parser provides value, flag, status; conversion provides normalized value/unit
            return array_merge($param, [
                'universal_code' => $mapping['universal_code'],
                'parameter_key' => $mapping['parameter_key'],
                'lab_test_id' => $mapping['lab_test_id'],
                'value' => $conversion['value'],
                'unit' => $conversion['unit'] ?? $param['unit'] ?? null,
                'ref_low' => $mapping['ref_low'] ?? $param['ref_low'] ?? null,
                'ref_high' => $mapping['ref_high'] ?? $param['ref_high'] ?? null,
                'ref_range' => $mapping['ref_range_text'] ?? $param['ref_range'] ?? null,
                'mapped' => $mapping['mapped'],
                'conversion_warning' => $conversion['warning'] ?? null,
            ]);
        }, $parsed['parameters'] ?? []);

        // Filter out skipped/empty parameters added by vendor parser
        $parsed['parameters'] = array_values(array_filter(
            $parsed['parameters'],
            fn ($p) => isset($p['value']) && $p['value'] !== '' && $p['value'] !== null
        ));

        return $parsed;
    }

    /**
     * Default capabilities — vendors override.
     */
    public function capabilities(): array
    {
        return [
            'result_upload' => true,
            'worklist' => false,
            'query' => false,
            'bidirectional' => false,
        ];
    }

    /**
     * Default worklist builder — vendors override for bidirectional.
     */
    public function buildWorklist(array $orderData, LabAnalyzer $analyzer): ?string
    {
        return null;
    }

    /**
     * Default vendor matcher — vendors override.
     */
    public function matchesVendor(string $handshakePayload): bool
    {
        return false;
    }
}
