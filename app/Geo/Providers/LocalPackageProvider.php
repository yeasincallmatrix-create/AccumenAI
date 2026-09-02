<?php

namespace App\Geo\Providers;

use App\Geo\Contracts\GeoDataProvider;
use App\Models\Country;
use Generator;

/**
 * Reads a git-hub country data package from the filesystem.
 *
 * A package is either a single file or a directory containing one or more
 * data files plus (optionally) a metadata.json file that carries the target
 * country and level labels.
 *
 * Supported record formats (all streamed, never fully loaded into memory):
 *   .jsonl / .ndjson  — one JSON object per line
 *   .json             — a top-level array of flat objects (streamed)
 *   .csv              — header row + one record per line
 *
 * Directory layout example:
 *
 *   database/geo/UnitedStates/
 *       metadata.json
 *       locations.jsonl
 *
 * For very large countries the directory may hold multiple data files
 * (California.jsonl, Texas.jsonl, ...) which are streamed in filename order.
 */
class LocalPackageProvider implements GeoDataProvider
{
    private string $path;

    private ?Country $country = null;

    /**
     * @param  int  $startLine  0-based index of the first record to yield. Allows
     *                          the admin UI to resume a long import in batches:
     *                          each poll constructs the provider with the number
     *                          of records already consumed.
     */
    public function __construct(string $path, private int $startLine = 0)
    {
        $this->path = $path;
    }

    public function records(): iterable
    {
        $skippedRecords = 0;

        foreach ($this->files() as $file) {
            $rows = match ($this->format($file)) {
                'jsonl' => $this->jsonl($file),
                'csv' => $this->csv($file),
                default => $this->json($file),
            };
            foreach ($rows as $raw) {
                if (! is_array($raw)) {
                    continue;
                }
                $normalized = $this->normalize($raw);
                if ($normalized === null) {
                    continue;
                }
                if ($skippedRecords < $this->startLine) {
                    $skippedRecords++;

                    continue;
                }
                yield $skippedRecords => $normalized;
                $skippedRecords++;
            }
        }
    }

    public function providedCountry(): ?Country
    {
        if ($this->country !== null) {
            return $this->country;
        }

        $metadata = $this->metadata();
        $iso2 = strtoupper((string) ($metadata['country']['iso2'] ?? $metadata['iso2'] ?? (string) ($metadata['country_code'] ?? '')));

        if ($iso2 === '') {
            return null;
        }

        return $this->country = Country::where('iso2', $iso2)->first();
    }

    public function metadata(): array
    {
        $candidates = [
            $this->path.'/metadata.json',
            is_file($this->path) ? dirname($this->path).'/metadata.json' : '',
        ];
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                $decoded = json_decode((string) file_get_contents($candidate), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }

    /** Candidate data files in filename order. */
    private function files(): array
    {
        if (is_file($this->path)) {
            return [$this->path];
        }
        if (! is_dir($this->path)) {
            return [];
        }

        $files = glob($this->path.'/*') ?: [];
        sort($files, SORT_STRING);

        return array_values(array_filter($files, function (string $file) {
            if (! is_file($file)) {
                return false;
            }
            if (basename($file) === 'metadata.json') {
                return false;
            }

            return in_array($this->format($file), ['jsonl', 'json', 'csv'], true);
        }));
    }

    private function format(string $file): string
    {
        return strtolower(pathinfo($file, PATHINFO_EXTENSION));
    }

    /** One JSON object per line — the recommended large-file format. */
    private function jsonl(string $file): Generator
    {
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            return;
        }
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                yield $decoded;
            }
        }
        fclose($fh);
    }

    /**
     * Streams a top-level JSON array of objects without loading it whole.
     * Records are expected to be flat (name/value or simple key/object values).
     */
    private function json(string $file): Generator
    {
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            return;
        }

        $buffer = '';
        $depth = 0;       // object/array nesting outside strings
        $inString = false;
        $escaped = false;
        $element = '';

        while (($chunk = fread($fh, 65536)) !== false && $chunk !== '') {
            for ($i = 0, $len = strlen($chunk); $i < $len; $i++) {
                $char = $chunk[$i];

                if ($inString) {
                    $element .= $char;
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\') {
                        $escaped = true;
                    } elseif ($char === '"') {
                        $inString = false;
                    }

                    continue;
                }

                if ($char === '"') {
                    $inString = true;
                    $element .= $char;

                    continue;
                }

                if ($char === '{' || $char === '[') {
                    $depth++;
                    $element .= $char;

                    continue;
                }

                if ($char === '}' || $char === ']') {
                    $depth--;
                    $element .= $char;
                    if ($depth <= 0 && trim($element) !== '') {
                        $decoded = json_decode(trim($element), true);
                        $element = '';
                        if (is_array($decoded)) {
                            yield $decoded;
                        }
                    }

                    continue;
                }

                $element .= $char;
                if ($depth === 0 && $char === ',') {
                    continue;
                }
            }
        }

        if (trim($element) !== '') {
            $decoded = json_decode(trim($element), true);
            if (is_array($decoded)) {
                yield $decoded;
            }
        }
        fclose($fh);
    }

    /** Header row maps CSV columns; rows are streamed one at a time. */
    private function csv(string $file): Generator
    {
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            return;
        }

        $header = null;
        while (($row = fgetcsv($fh, 0, ',')) !== false) {
            if ($row === [null]) {
                continue;
            }
            if ($header === null) {
                $header = array_map(fn ($h) => trim((string) $h), $row);

                continue;
            }
            $assoc = [];
            foreach ($header as $idx => $key) {
                $assoc[$key] = $row[$idx] ?? null;
            }
            yield $assoc;
        }
        fclose($fh);
    }

    /** Map package fields onto the normalized provider contract. */
    private function normalize(array $raw): ?array
    {
        $level = (int) ($raw['level'] ?? $raw['administrative_level'] ?? 0);
        if ($level < 1 || $level > 3) {
            return null;
        }

        $code = trim((string) ($raw['code'] ?? $raw['external_id'] ?? $raw['id'] ?? ''));
        $name = trim((string) ($raw['name'] ?? ''));

        if ($code === '' || $name === '') {
            return null;
        }

        return [
            'level' => $level,
            'code' => $code,
            'name' => $name,
            'parent_code' => $this->nullableString($raw, ['parent_code', 'parent_external_id', 'parentId']),
            'postal_code' => $this->nullableString($raw, ['postal_code', 'zip']),
            'latitude' => $this->nullableString($raw, ['latitude', 'lat']),
            'longitude' => $this->nullableString($raw, ['longitude', 'lon', 'lng']),
        ];
    }

    private function nullableString(array $raw, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($raw[$key]) && $raw[$key] !== '') {
                return trim((string) $raw[$key]);
            }
        }

        return null;
    }
}
