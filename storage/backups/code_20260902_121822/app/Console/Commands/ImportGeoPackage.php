<?php

namespace App\Console\Commands;

use App\Geo\Providers\LocalPackageProvider;
use App\Models\Country;
use App\Services\GeoImportService;
use Illuminate\Console\Command;

/**
 * Import a country geography data package into the shared reference tables.
 *
 * A package is a single file or a directory of data files (.jsonl/.json/.csv)
 * plus an optional metadata.json that declares the target country and level
 * labels. See the GeoDataProvider contract and LocalPackageProvider for the
 * exact accepted formats.
 *
 * Unlike `geo:import` (which consumes raw GeoNames dumps on a fixed schedule),
 * this command imports one finished, curated country package — the same engine
 * the Super Admin upload UI uses.
 *
 * Examples:
 *   php artisan geo:import-package database/geo/UnitedStates
 *   php artisan geo:import-package database/geo/BD.jsonl --country=BD
 *   php artisan geo:import-package database/geo/BD.jsonl --validate
 */
class ImportGeoPackage extends Command
{
    protected $signature = 'geo:import-package
                            {path : Path to the package file or directory.}
                            {--country= : Override/confirm the ISO2 country code. When omitted the package metadata is used.}
                            {--validate : Validate the package without writing to the database.}
                            {--chunk= : Records per database transaction (defaults to geo.import.chunk_size).}';

    protected $description = 'Import a curated country geography package (jsonl/json/csv).';

    public function handle(GeoImportService $service): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path) && ! is_dir($path)) {
            $this->error("Path not found: {$path}");

            return self::FAILURE;
        }

        $provider = new LocalPackageProvider($path);

        $country = $this->resolveCountry($provider);
        if ($country === null) {
            $this->error('Could not determine the target country. Pass --country=ISO2 or add a metadata.json to the package.');

            return self::FAILURE;
        }

        if ($this->option('chunk') !== null) {
            $service = new GeoImportService((int) $this->option('chunk'));
        }

        $this->info(($this->option('validate') ? 'Validating' : 'Importing')." package for {$country->name} ({$country->iso2})…");

        $report = $this->option('validate')
            ? $service->validate($provider, $country)
            : $service->import($provider, $country);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Status', $report['status']],
                ['Records read', number_format($report['total'])],
                ['Inserted', number_format($report['inserted'])],
                ['Updated', number_format($report['updated'])],
                ['Skipped', number_format($report['skipped'])],
                ['Duplicates detected', number_format($report['duplicates'])],
                ['Errors', number_format($report['errors'])],
            ]
        );

        if ($report['error_summary'] !== null) {
            $this->warn(trim($report['error_summary']));
        }

        return $report['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveCountry(LocalPackageProvider $provider): ?Country
    {
        $iso2 = $this->option('country');
        if ($iso2 !== null && $iso2 !== '') {
            return Country::where('iso2', strtoupper((string) $iso2))->first();
        }

        return $provider->providedCountry();
    }
}
