<?php

namespace App\Services\LabIntegration;

use App\Services\LabIntegration\Parsers\AstmParser;
use App\Services\LabIntegration\Parsers\CsvParser;
use App\Services\LabIntegration\Parsers\Hl7Parser;
use InvalidArgumentException;

class ParserFactory
{
    public function __construct(
        protected AstmParser $astm,
        protected Hl7Parser $hl7,
        protected CsvParser $csv,
    ) {}

    /**
     * Resolve parser by protocol string from lab_analyzers.protocol.
     */
    public function for(string $protocol)
    {
        return match (strtolower($protocol)) {
            'astm' => $this->astm,
            'hl7', 'hl7v2' => $this->hl7,
            'csv', 'file', 'raw_text', 'vendor' => $this->csv,
            default => throw new InvalidArgumentException("Unsupported protocol: {$protocol}"),
        };
    }
}
