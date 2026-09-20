<?php

namespace App\Services\LabIntegration;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;

class ParameterMapImportService
{
    public const REQUIRED_COLUMNS = ['vendor_code', 'universal_code'];

    /**
     * Import vendor→universal maps for one analyzer from a CSV file.
     *
     * Returns ['imported' => int, 'updated' => int, 'skipped' => int, 'errors' => string[]].
     * Never throws on bad rows — they land in skipped/errors.
     */
    public function import(LabAnalyzer $analyzer, string $csvPath): array
    {
        $result = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        if (! is_readable($csvPath)) {
            $result['errors'][] = 'CSV file unreadable.';

            return $result;
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $result['errors'][] = 'CSV file could not be opened.';

            return $result;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $result['errors'][] = 'CSV is empty.';

            return $result;
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);
        if (! empty($missing)) {
            fclose($handle);
            $result['errors'][] = 'Missing required columns: '.implode(', ', $missing);

            return $result;
        }

        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if (count($row) !== count($header)) {
                $result['skipped']++;
                $result['errors'][] = "Line {$line}: column count mismatch.";

                continue;
            }
            $data = array_combine($header, array_map(fn ($v) => trim((string) $v), $row));

            if ($data['vendor_code'] === '' || $data['universal_code'] === '') {
                $result['skipped']++;
                $result['errors'][] = "Line {$line}: vendor_code and universal_code are required.";

                continue;
            }

            try {
                $map = LabAnalyzerParameterMap::updateOrCreate(
                    ['analyzer_id' => $analyzer->id, 'vendor_code' => $data['vendor_code']],
                    [
                        'institute_id' => $analyzer->institute_id,
                        'vendor_name' => $data['vendor_name'] ?? null,
                        'universal_code' => $data['universal_code'],
                        'parameter_key' => $data['parameter_key'] ?? $data['universal_code'],
                        'unit_from' => $data['unit_from'] ?? null,
                        'unit_to' => $data['unit_to'] ?? null,
                        'conversion_factor' => is_numeric($data['conversion_factor'] ?? null) ? $data['conversion_factor'] : 1,
                        'ref_low' => is_numeric($data['ref_low'] ?? null) ? $data['ref_low'] : null,
                        'ref_high' => is_numeric($data['ref_high'] ?? null) ? $data['ref_high'] : null,
                        'ref_range_text' => $data['ref_range_text'] ?? null,
                        'is_active' => true,
                    ]
                );

                if ($map->wasRecentlyCreated) {
                    $result['imported']++;
                } else {
                    $result['updated']++;
                }
            } catch (\Throwable $e) {
                $result['skipped']++;
                $result['errors'][] = "Line {$line}: ".$e->getMessage();
            }
        }

        fclose($handle);

        return $result;
    }
}
