<?php

namespace App\Services\LabIntegration\Parsers;

use App\Contracts\LabIntegration\UniversalResult;

/**
 * CSV / raw-text parser for semi-automated analyzers.
 * Two modes (auto-detected):
 *   - CSV with header: recognized columns
 *   - Raw text with regex: "WBC  7.2  10^3/uL  N"
 *
 * Never throws on malformed input — problems land in parse_errors.
 */
class CsvParser
{
    public function parse(string $rawPayload): array
    {
        try {
            $normalized = str_replace(["\r\n", "\r"], "\n", trim($rawPayload));
            $lines = array_values(array_filter(explode("\n", $normalized), fn ($l) => trim($l) !== ''));
        } catch (\Throwable $e) {
            return UniversalResult::fromArray(['parse_errors' => ['input normalization failed: '.$e->getMessage()]]);
        }

        if ($this->looksLikeCsv($lines)) {
            return $this->parseCsv($lines);
        }

        return $this->parseRawText($lines);
    }

    protected function looksLikeCsv(array $lines): bool
    {
        if (empty($lines)) {
            return false;
        }
        $first = reset($lines);

        return str_contains($first, ',') || str_contains($first, ';') || str_contains($first, "\t");
    }

    protected function parseCsv(array $lines): array
    {
        $errors = [];
        $parameters = [];
        $accession = null;
        $sampleBarcode = null;
        $patientHint = null;

        try {
            $header = str_getcsv(array_shift($lines));
        } catch (\Throwable $e) {
            return UniversalResult::fromArray(['parse_errors' => ['CSV header unreadable: '.$e->getMessage()]]);
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $expectedColumns = ['test_code', 'value', 'unit', 'flag', 'ref_range'];
        $missing = array_diff($expectedColumns, $header);
        if (! empty($missing)) {
            $errors[] = 'CSV header missing: '.implode(', ', $missing);

            return UniversalResult::fromArray(['parse_errors' => $errors]);
        }

        foreach ($lines as $i => $line) {
            try {
                $row = str_getcsv($line);
            } catch (\Throwable $e) {
                $errors[] = 'Row '.($i + 2).': unreadable';

                continue;
            }
            if (count($row) !== count($header)) {
                $errors[] = 'Row '.($i + 2).': column count mismatch';

                continue;
            }
            $data = array_combine($header, $row);

            // Extract optional metadata
            if (! $accession && ! empty($data['accession_number'])) {
                $accession = $data['accession_number'];
            }
            if (! $sampleBarcode && ! empty($data['sample_barcode'])) {
                $sampleBarcode = $data['sample_barcode'];
            }
            if (! $patientHint && ! empty($data['patient_id'])) {
                $patientHint = $data['patient_id'];
            }

            $vendorCode = trim($data['test_code'] ?? '');
            $value = trim($data['value'] ?? '');
            if ($vendorCode === '' || $value === '') {
                $errors[] = 'Row '.($i + 2).': empty test_code or value';

                continue;
            }

            [$refLow, $refHigh, $refText] = $this->parseRefRange($data['ref_range'] ?? null);

            $parameters[] = [
                'vendor_code' => $vendorCode,
                'value' => $value,
                'unit' => trim($data['unit'] ?? '') ?: null,
                'flag' => $this->normalizeFlag($data['flag'] ?? null),
                'ref_range' => $refText,
                'ref_low' => $refLow,
                'ref_high' => $refHigh,
                'status' => 'F',
            ];
        }

        return UniversalResult::fromArray([
            'accession_number' => $accession,
            'sample_barcode' => $sampleBarcode,
            'patient_hint' => $patientHint,
            'parameters' => $parameters,
            'raw_metadata' => ['format' => 'csv'],
            'parse_errors' => $errors,
        ]);
    }

    protected function parseRawText(array $lines): array
    {
        $errors = [];
        $parameters = [];
        $accession = null;

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Look for accession/sample ID
            if (preg_match('/(?:sample|accession|specimen)\s*(?:id|no|#)?[:\s]+([A-Z0-9\-_]+)/i', $line, $m)) {
                $accession = $m[1];

                continue;
            }

            // Result line: TEST  VALUE  [UNIT]  [FLAG]
            // Example: "WBC    7.2    10^3/uL    N"
            if (preg_match('/^([A-Z][A-Z0-9_]{1,15})\s+(-?[\d.]+)\s*([^\s]+)?\s*(HH|LL|[HLN*])?\s*$/i', $line, $m)) {
                $parameters[] = [
                    'vendor_code' => strtoupper($m[1]),
                    'value' => $m[2],
                    'unit' => $m[3] ?? null,
                    'flag' => $this->normalizeFlag($m[4] ?? null),
                    'ref_range' => null,
                    'ref_low' => null,
                    'ref_high' => null,
                    'status' => 'F',
                ];
            } else {
                $errors[] = 'Line '.($i + 1).': unparseable';
            }
        }

        return UniversalResult::fromArray([
            'accession_number' => $accession,
            'sample_barcode' => $accession,
            'parameters' => $parameters,
            'raw_metadata' => ['format' => 'raw_text', 'line_count' => count($lines)],
            'parse_errors' => $errors,
        ]);
    }

    protected function parseRefRange(?string $range): array
    {
        if (! $range) {
            return [null, null, null];
        }
        if (preg_match('/^(-?[\d.]+)\s*[-–]\s*(-?[\d.]+)$/', trim($range), $m)) {
            return [(float) $m[1], (float) $m[2], $range];
        }

        return [null, null, $range];
    }

    protected function normalizeFlag(?string $flag): ?string
    {
        if (! $flag) {
            return null;
        }
        $flag = strtoupper(trim($flag));

        return match ($flag) {
            'N', 'H', 'L', 'HH', 'LL' => $flag,
            'A', '*' => '*',
            default => null,
        };
    }
}
