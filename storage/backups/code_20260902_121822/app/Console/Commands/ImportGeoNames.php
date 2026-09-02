<?php

namespace App\Console\Commands;

use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use Illuminate\Console\Command;
use ZipArchive;

/**
 * Imports the global administrative hierarchy from GeoNames dumps.
 *
 * Source files (place them in storage/geo/, see geo:import --help):
 *   - countryInfo.txt      (https://download.geonames.org/export/dump/countryInfo.txt)
 *   - admin1CodesASCII.txt (https://download.geonames.org/export/dump/admin1CodesASCII.txt)
 *   - admin2Codes.txt      (https://download.geonames.org/export/dump/admin2Codes.txt)
 *   - adminCode5.zip       (https://download.geonames.org/export/dump/adminCode5.zip)
 *     → contains adminCode3.txt which feeds level 3.
 *
 * The import is idempotent (country keyed by iso2, unit keyed by country+code)
 * and country-neutral: every country resolves through the same
 * countries → administrative_levels → administrative_units hierarchy.
 *
 * Countries with fewer than 3 administrative levels simply get fewer level rows
 * — the UI disables/hides the unused slots. Countries with more than 3 levels
 * expose the first three (the most commonly used for address selection).
 */
class ImportGeoNames extends Command
{
    protected $signature = 'geo:import
                            {--countries= : Comma-separated ISO2 whitelist, e.g. US,IN,GB. Imports all when omitted.}
                            {--clear : Delete existing geo data for the imported countries before inserting.}
                            {--pretend : Print what would be imported without writing.}';

    protected $description = 'Import countries + 3 administrative levels from GeoNames dumps stored in storage/geo.';

    private string $dir = '';

    private const MAX_UNITS = 800000;

    public function handle(): int
    {
        $this->dir = storage_path('geo');
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }

        $countryFile = $this->dir.'/countryInfo.txt';
        $admin1File = $this->dir.'/admin1CodesASCII.txt';
        $admin2File = $this->dir.'/admin2Codes.txt';
        $admin3File = $this->ensureAdmin3File();

        $missing = array_filter([
            'countryInfo.txt' => is_file($countryFile),
            'admin1CodesASCII.txt' => is_file($admin1File),
            'admin2Codes.txt' => is_file($admin2File),
            'adminCode3.txt' => $admin3File !== null && is_file($admin3File),
        ], fn ($ok) => ! $ok);

        if ($missing !== []) {
            foreach ($missing as $file => $_) {
                $this->error("Missing source file: storage/geo/{$file}");
            }
            $this->line('Download from https://download.geonames.org/export/dump/ and retry.');

            return self::FAILURE;
        }

        $whitelist = $this->option('countries')
            ? array_filter(array_map('trim', explode(',', (string) $this->option('countries'))))
            : [];

        $this->importCountries($countryFile, $whitelist);
        $this->importLevels($whitelist);
        $this->importUnits($admin1File, 1, $whitelist);
        $this->importUnits($admin2File, 2, $whitelist);
        $this->importUnits((string) $admin3File, 3, $whitelist);

        $this->line('');
        $this->info('Geo import finished. See counts below:');
        $this->info('  countries      : '.number_format(Country::count()));
        $this->info('  levels         : '.number_format(AdministrativeLevel::count()));
        $this->info('  units          : '.number_format(AdministrativeUnit::count()));

        return self::SUCCESS;
    }

    /** Extract adminCode3.txt from adminCode5.zip when present. */
    private function ensureAdmin3File(): ?string
    {
        $zip = $this->dir.'/adminCode5.zip';
        $out = $this->dir.'/adminCode3.txt';

        if (is_file($out)) {
            return $out;
        }
        if (! is_file($zip)) {
            return null;
        }
        if (! class_exists('ZipArchive')) {
            $this->error('adminCode5.zip present but ZipArchive is unavailable; extract adminCode3.txt manually.');

            return null;
        }

        $za = new ZipArchive;
        if ($za->open($zip) !== true) {
            return null;
        }
        $za->extractTo($this->dir, ['adminCode3.txt']);
        $za->close();

        return is_file($out) ? $out : null;
    }

    private function importCountries(string $file, array $whitelist): void
    {
        $pretend = $this->option('pretend');
        $clear = $this->option('clear');
        $imported = 0;

        $rows = $this->tabRows($file);
        foreach ($rows as $row) {
            if (count($row) < 5 || $row[0] === '' || str_starts_with($row[0], '#')) {
                continue;
            }
            $iso2 = $row[0];
            if ($whitelist !== [] && ! in_array($iso2, $whitelist, true)) {
                continue;
            }
            $iso3 = $row[1] ?? null;
            $name = $row[4] ?? $iso2;
            $phone = $row[12] ?? null;

            if ($clear && ! $pretend) {
                Country::where('iso2', $iso2)->delete();
            }

            if ($pretend) {
                $this->line("  [pretend] country {$iso2} {$name}");
                $imported++;

                continue;
            }

            Country::updateOrCreate(['iso2' => $iso2], [
                'name' => $name,
                'iso3' => $iso3,
                'phone_code' => $phone,
                'status' => true,
            ]);
            $imported++;
        }

        $this->info("Countries: {$imported} processed.");
    }

    private function importLevels(array $whitelist): void
    {
        $pretend = $this->option('pretend');

        $countries = Country::query()->orderBy('id')->get();
        foreach ($countries as $country) {
            if ($whitelist !== [] && ! in_array($country->iso2, $whitelist, true)) {
                continue;
            }
            $labels = $this->labelsFor($country->iso2);

            foreach ([1 => 'admin_level_1', 2 => 'admin_level_2', 3 => 'admin_level_3'] as $level => $slug) {
                if ($pretend) {
                    $this->line("  [pretend] level {$country->iso2} {$level} ".$labels[$level]);

                    continue;
                }
                AdministrativeLevel::updateOrCreate(
                    ['country_id' => $country->id, 'level_number' => $level],
                    ['name' => $labels[$level], 'slug' => $slug, 'status' => true]
                );
            }
        }
    }

    /**
     * Import units for one admin level. Parent resolution is derived from the
     * dotted GeoNames code: level-2 parent = level-1 code, level-3 parent =
     * level-2 code (all within the same country).
     */
    private function importUnits(string $file, int $level, array $whitelist): void
    {
        $pretend = $this->option('pretend');

        $rows = $this->tabRows($file);
        $this->info('Parsing level '.$level.' source ('.$this->countFileLines($file).' lines)...');

        $count = 0;
        $levelCache = [];   // "iso2|L" => level id
        $parentCache = [];  // "iso2|code" => unit id (for the parent level)

        foreach ($rows as $row) {
            if (count($row) < 2 || $row[0] === '' || str_starts_with($row[0], '#')) {
                continue;
            }
            $code = trim($row[0]);   // e.g. US.CA  / US.CA.001  / US.CA.001.001
            $name = trim($row[1]);

            $parts = explode('.', $code);
            $iso2 = $parts[0] ?? '';
            if ($iso2 === '' || ($whitelist !== [] && ! in_array($iso2, $whitelist, true))) {
                continue;
            }

            $country = Country::where('iso2', $iso2)->first();
            if (! $country) {
                continue;
            }

            $levelKey = "{$iso2}|{$level}";
            if (! isset($levelCache[$levelKey])) {
                $levelCache[$levelKey] = AdministrativeLevel::where('country_id', $country->id)
                    ->where('level_number', $level)
                    ->value('id');
            }
            $levelId = $levelCache[$levelKey];
            if (! $levelId) {
                continue;
            }

            $parentId = null;
            if ($level > 1) {
                $parentCode = implode('.', array_slice($parts, 0, $level));
                $parentKey = "{$iso2}|{$parentCode}";
                if (! isset($parentCache[$parentKey])) {
                    $parentCache[$parentKey] = AdministrativeUnit::where('country_id', $country->id)
                        ->where('code', $parentCode)
                        ->value('id');
                }
                $parentId = $parentCache[$parentKey] ?: null;
            }

            if ($pretend) {
                $count++;

                continue;
            }

            AdministrativeUnit::updateOrCreate(
                ['country_id' => $country->id, 'code' => $code],
                [
                    'administrative_level_id' => $levelId,
                    'parent_id' => $parentId,
                    'name' => $name,
                    'status' => true,
                ]
            );

            if (++$count >= self::MAX_UNITS) {
                $this->warn("Hit MAX_UNITS ({self::MAX_UNITS}); stopping level {$level} import.");

                break;
            }
        }

        $this->info("Level {$level} units: {$count} processed.");
    }

    private function labelsFor(string $iso2): array
    {
        $labels = config('geo-labels.labels');
        $defaults = config('geo-labels.defaults');

        return $labels[$iso2] ?? $defaults;
    }

    /** Tab-separated rows, skipping comments. */
    private function tabRows(string $file): \Generator
    {
        $fh = fopen($file, 'r');
        if (! $fh) {
            return;
        }
        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            yield explode("\t", $line);
        }
        fclose($fh);
    }

    /** Count data lines in a tab-separated source without consuming a generator. */
    private function countFileLines(string $file): int
    {
        $n = 0;
        $fh = fopen($file, 'r');
        if (! $fh) {
            return 0;
        }
        while (($line = fgets($fh)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $n++;
        }
        fclose($fh);

        return $n;
    }
}
