<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Step 21 — minimal streaming CSV download.
 *
 * Writes a UTF-8 BOM followed by the header row and streams the data rows
 * straight to the output, so a large export never builds a full file in
 * memory. `fputcsv` handles quoting of field commas, quotes and newlines;
 * every cell is normalized to a string so sparse rows stay well-formed.
 */
final class CsvStream
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    static fn (mixed $value): string => (string) ($value ?? ''),
                    is_array($row) ? $row : iterator_to_array($row),
                ));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
