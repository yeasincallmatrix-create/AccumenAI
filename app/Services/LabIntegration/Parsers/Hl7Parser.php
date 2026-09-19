<?php

namespace App\Services\LabIntegration\Parsers;

use App\Contracts\LabIntegration\UniversalResult;

/**
 * HL7 v2.x ORU^R01 parser (generic — NOT vendor-specific).
 *
 * Segments separated by CR, fields by |, components by ^,
 * sub-components by &, escape character \.
 * Key segments: MSH, PID, OBR, OBX.
 *
 * Never throws on malformed input — problems land in parse_errors.
 */
class Hl7Parser
{
    public function parse(string $rawPayload): array
    {
        $errors = [];
        $parameters = [];
        $accession = null;
        $sampleBarcode = null;
        $patientHint = null;
        $messageId = null;
        $measuredAt = null;

        try {
            $normalized = str_replace(["\r\n", "\r"], "\n", $rawPayload);
            $segments = array_values(array_filter(explode("\n", trim($normalized)), fn ($s) => trim($s) !== ''));
        } catch (\Throwable $e) {
            return UniversalResult::fromArray(['parse_errors' => ['input normalization failed: '.$e->getMessage()]]);
        }

        foreach ($segments as $lineNum => $segment) {
            if ($segment === '') {
                continue;
            }

            $fields = explode('|', $segment);
            $segmentType = $fields[0] ?? '';

            try {
                switch ($segmentType) {
                    case 'MSH':
                        // MSH|^~\&|SendingApp|SendingFac|RecvApp|RecvFac|20260920103000||ORU^R01|MSG00001|P|2.5
                        $messageId = $this->firstNonEmpty([$fields[9] ?? null]) ?? $messageId;
                        $timestamp = $fields[6] ?? null;
                        if ($timestamp) {
                            $measuredAt = $this->parseHl7Timestamp($timestamp) ?? $measuredAt;
                        }
                        break;

                    case 'PID':
                        // PID|1||MRN-12345||DOE^JOHN||19800101|M
                        $patientHint = $this->firstNonEmpty([
                            $fields[3] ?? null, // Patient ID
                        ]) ?? $patientHint;
                        break;

                    case 'OBR':
                        // OBR|1|ACC-2026-00001|SAMPLE001|CBC^...|...
                        $found = $this->firstNonEmpty([
                            $fields[2] ?? null, // Filler/Placer order number
                            $fields[3] ?? null,
                        ]);
                        if ($found !== null) {
                            $accession = $found;
                        }
                        $barcode = $this->firstNonEmpty([$fields[3] ?? null]);
                        if ($barcode !== null) {
                            $sampleBarcode = $barcode;
                        }
                        break;

                    case 'OBX':
                        // OBX|1|NM|WBC^White Blood Cell Count||7.2|10^3/uL|4.0-11.0|N|||F|||...
                        $obsIdField = $fields[3] ?? ''; // WBC^White Blood Cell Count
                        $vendorCode = $this->extractIdentifier($obsIdField);
                        $vendorName = $this->extractText($obsIdField);

                        if (empty($vendorCode)) {
                            $errors[] = "Segment {$lineNum}: empty test code in OBX";
                            continue 2;
                        }

                        $value = $fields[5] ?? null;
                        if ($value === null || $value === '') {
                            $errors[] = "Segment {$lineNum}: empty value for {$vendorCode}";
                            continue 2;
                        }

                        [$refLow, $refHigh, $refText] = $this->parseRefRange($fields[7] ?? null);

                        $parameters[] = [
                            'vendor_code' => $vendorCode,
                            'vendor_name' => $vendorName,
                            'value' => $value,
                            'unit' => $fields[6] ?? null,
                            'ref_range' => $refText,
                            'ref_low' => $refLow,
                            'ref_high' => $refHigh,
                            'flag' => $this->normalizeFlag($fields[8] ?? null),
                            'status' => $fields[11] ?? 'F',
                        ];
                        break;
                }
            } catch (\Throwable $e) {
                $errors[] = "Segment {$lineNum} ({$segmentType}): ".$e->getMessage();
            }
        }

        return UniversalResult::fromArray([
            'accession_number' => $accession,
            'sample_barcode' => $sampleBarcode ?: $accession,
            'patient_hint' => $patientHint,
            'message_id' => $messageId,
            'measured_at' => $measuredAt,
            'parameters' => $parameters,
            'raw_metadata' => ['segment_count' => count($segments)],
            'parse_errors' => $errors,
        ]);
    }

    protected function extractIdentifier(string $field): ?string
    {
        $parts = explode('^', $field);

        return ($parts[0] ?? '') !== '' ? $parts[0] : null;
    }

    protected function extractText(string $field): ?string
    {
        $parts = explode('^', $field);

        return $parts[1] ?? null;
    }

    protected function parseRefRange(?string $range): array
    {
        if (! $range) {
            return [null, null, null];
        }
        // HL7 can use "4.0-11.0" or "4.0^11.0" or "<5.7"
        $range = str_replace('^', '-', trim($range));
        if (preg_match('/^(-?[\d.]+)\s*[-–]\s*(-?[\d.]+)$/', $range, $m)) {
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
            'A' => '*',
            default => null,
        };
    }

    protected function parseHl7Timestamp(string $ts): ?string
    {
        // HL7: YYYYMMDDHHMMSS[+/-ZZZZ]
        $clean = preg_replace('/[+\-]\d{4}$/', '', $ts);
        if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?(\d{2})?$/', $clean, $m)) {
            try {
                $str = $m[1].$m[2].$m[3]
                    .($m[4] ?? '00').($m[5] ?? '00').($m[6] ?? '00');

                return \Carbon\Carbon::createFromFormat('YmdHis', $str)->toIso8601String();
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
}
