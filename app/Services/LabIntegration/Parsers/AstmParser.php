<?php

namespace App\Services\LabIntegration\Parsers;

use App\Contracts\LabIntegration\UniversalResult;

/**
 * ASTM E1394 protocol parser (generic — NOT vendor-specific).
 *
 * Records separated by CR/LF, fields by |, components by ^.
 * Record types: H (header), P (patient), O (order), R (result),
 * C (comment), Q (query), L (terminator).
 *
 * Phase 3 vendor adapters will wrap this parser for quirks; the parser
 * itself never throws on malformed input — problems land in parse_errors.
 */
class AstmParser
{
    public function parse(string $rawPayload): array
    {
        $errors = [];
        $parameters = [];
        $accession = null;
        $patientHint = null;
        $messageId = null;
        $measuredAt = null;

        try {
            // Normalize line endings
            $normalized = str_replace(["\r\n", "\r"], "\n", $rawPayload);
            $records = array_values(array_filter(explode("\n", trim($normalized)), fn ($r) => trim($r) !== ''));
        } catch (\Throwable $e) {
            return UniversalResult::fromArray(['parse_errors' => ['input normalization failed: '.$e->getMessage()]]);
        }

        foreach ($records as $lineNum => $record) {
            if ($record === '') {
                continue;
            }

            $fields = explode('|', $record);
            $recordType = $fields[0] ?? '';

            try {
                switch ($recordType) {
                    case 'H': // Header
                        // H|\^&|||AnalyzerName^Model^Version|...
                        $messageId = $this->firstNonEmpty([$fields[13] ?? null]);
                        break;

                    case 'P': // Patient
                        // P|1||MRN-12345||DOE^JOHN||19800101|M
                        $patientHint = $this->firstNonEmpty([
                            $fields[3] ?? null, // patient ID
                        ]) ?? $patientHint;
                        break;

                    case 'O': // Order
                        // O|1|ACC-2026-00001||^^^CBC|...
                        $found = $this->firstNonEmpty([
                            $fields[2] ?? null, // sample ID / accession
                            $fields[3] ?? null,
                        ]);
                        if ($found !== null) {
                            $accession = $found;
                        }
                        // Timing: ASTM O-record layouts vary by vendor, so scan
                        // for the first 14-digit YYYYMMDDHHMMSS field.
                        $timestamp = $this->findTimestamp(array_slice($fields, 1));
                        if ($timestamp) {
                            $measuredAt = $this->parseAstmTimestamp($timestamp) ?? $measuredAt;
                        }
                        break;

                    case 'R': // Result
                        // R|1|^^^WBC|7.2|10^3/uL|4.0-11.0|N||F||...
                        $testField = $fields[2] ?? ''; // ^^^WBC
                        $vendorCode = $this->extractTestCode($testField);
                        if (empty($vendorCode)) {
                            $errors[] = "Record {$lineNum}: empty test code";
                            continue 2;
                        }

                        $value = $fields[3] ?? null;
                        if ($value === null || $value === '') {
                            $errors[] = "Record {$lineNum}: empty value for {$vendorCode}";
                            continue 2;
                        }

                        [$refLow, $refHigh, $refText] = $this->parseRefRange($fields[5] ?? null);

                        $parameters[] = [
                            'vendor_code' => $vendorCode,
                            'value' => $value,
                            'unit' => $fields[4] ?? null,
                            'ref_range' => $refText,
                            'ref_low' => $refLow,
                            'ref_high' => $refHigh,
                            'flag' => $this->normalizeFlag($fields[6] ?? null),
                            'status' => $fields[8] ?? 'F',
                        ];
                        break;

                    case 'L': // Terminator
                        // L|1|N
                        break;

                    default:
                        // Unknown record type (C/Q comments/queries) — don't fail, just note
                        break;
                }
            } catch (\Throwable $e) {
                $errors[] = "Record {$lineNum} ({$recordType}): ".$e->getMessage();
            }
        }

        return UniversalResult::fromArray([
            'accession_number' => $accession,
            'sample_barcode' => $accession,
            'patient_hint' => $patientHint,
            'message_id' => $messageId,
            'measured_at' => $measuredAt,
            'parameters' => $parameters,
            'raw_metadata' => ['record_count' => count($records)],
            'parse_errors' => $errors,
        ]);
    }

    protected function extractTestCode(string $field): ?string
    {
        // ^^^WBC → WBC (last non-empty component)
        $parts = array_values(array_filter(explode('^', $field), fn ($p) => $p !== ''));
        $last = end($parts);

        return $last !== false && $last !== '' ? $last : null;
    }

    protected function parseRefRange(?string $range): array
    {
        if (! $range) {
            return [null, null, null];
        }
        if (preg_match('/^(-?[\d.]+)\s*[-–]\s*(-?[\d.]+)$/', trim($range), $m)) {
            return [(float) $m[1], (float) $m[2], $range];
        }
        if (preg_match('/^[<>]\s*(-?[\d.]+)$/', trim($range), $m)) {
            return [null, null, $range];
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
            'N', 'H', 'L', 'HH', 'LL', '*' => $flag,
            'A' => '*',
            default => null,
        };
    }

    protected function parseAstmTimestamp(string $ts): ?string
    {
        // ASTM: YYYYMMDDHHMMSS
        if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $ts, $m)) {
            try {
                return \Carbon\Carbon::createFromFormat(
                    'YmdHis',
                    "{$m[1]}{$m[2]}{$m[3]}{$m[4]}{$m[5]}{$m[6]}"
                )->toIso8601String();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    protected function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return trim((string) $v);
            }
        }

        return null;
    }

    protected function findTimestamp(array $fields): ?string
    {
        foreach ($fields as $field) {
            if (is_string($field) && preg_match('/^\d{14}$/', trim($field))) {
                return trim($field);
            }
        }

        return null;
    }
}
